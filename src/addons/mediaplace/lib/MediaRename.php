<?php

namespace FriendsOfRedaxo\Mediaplace;

use rex;
use rex_article_cache;
use rex_extension;
use rex_extension_point;
use rex_logger;
use rex_media;
use rex_media_cache;
use rex_media_manager;
use rex_path;
use rex_response;
use rex_sql;
use rex_sql_column;
use rex_sql_index;
use rex_sql_table;
use rex_string;
use rex_user;
use RuntimeException;
use Throwable;

/**
 * Dateien im Medienpool umbenennen, inklusive aller Verweise in der Datenbank.
 *
 * Ablauf: Die Datei wird sofort unter dem neuen Namen angelegt und in rex_media umgestellt. Die alte Datei
 * bleibt als Übergangskopie liegen, damit Seiten, die noch auf den alten Namen verweisen, weiter funktionieren.
 * Die Verweise in allen REDAXO-Tabellen ersetzt ein Job im Hintergrund, in kleinen Schritten mit gespeichertem
 * Fortschritt (API nach der Antwort, Backend-Seiten, Cronjob, Konsole). Am Ende werden die Übergangskopie
 * gelöscht und die betroffenen Caches geleert.
 */
final class MediaRename
{
    public const PERM = 'mediaplace[rename_files]';
    public const CSRF = 'mediaplace_rename';

    private const LOCK = 'mediaplace_rename_worker';
    private const BATCH = 100;
    private const TEXT_TYPES = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json'];

    /** @return non-empty-string */
    public static function table(): string
    {
        return rex::getTable('mediaplace_rename_job');
    }

    public static function ensureSchema(): void
    {
        rex_sql_table::get(self::table())
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new rex_sql_column('old_filename', 'varchar(255)'))
            ->ensureColumn(new rex_sql_column('new_filename', 'varchar(255)'))
            ->ensureColumn(new rex_sql_column('status', 'varchar(20)', false, 'running'))
            ->ensureColumn(new rex_sql_column('state', 'longtext', true))
            ->ensureColumn(new rex_sql_column('replaced', 'int(10) unsigned', false, '0'))
            ->ensureColumn(new rex_sql_column('error', 'text', true))
            ->ensureColumn(new rex_sql_column('createuser', 'varchar(255)'))
            ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
            ->ensureColumn(new rex_sql_column('updatedate', 'datetime'))
            ->ensureIndex(new rex_sql_index('status', ['status']))
            ->ensure();
    }

    /** Recht zum Umbenennen; mit Datei zusätzlich Zugriff auf deren Kategorie */
    public static function canRename(?rex_media $media = null): bool
    {
        $user = rex::getUser();
        if (!$user instanceof rex_user || (!$user->isAdmin() && !$user->hasPerm(self::PERM))) {
            return false;
        }

        return null === $media || MediaPermission::hasCategoryAccess($media->getCategoryId());
    }

    /**
     * Endgültiger Dateiname aus der Eingabe: wie REDAXO normalisiert, Endung der bisherigen Datei.
     */
    public static function targetName(string $oldFilename, string $input): string
    {
        $ext = strtolower(pathinfo($oldFilename, PATHINFO_EXTENSION));
        $base = trim($input);
        if ('' !== $ext && str_ends_with(strtolower($base), '.' . $ext)) {
            $base = substr($base, 0, -strlen($ext) - 1);
        }
        $base = rex_string::normalize($base, '_', '-@');
        $base = trim($base, '_');
        if ('' === $base) {
            return '';
        }

        return $base . ('' !== $ext ? '.' . $ext : '');
    }

    /**
     * Fehlermeldung (i18n-Schlüssel), wenn der Name nicht verwendet werden kann, sonst null.
     */
    public static function validate(string $oldFilename, string $newFilename): ?string
    {
        if ('' === $newFilename) {
            return 'mediaplace_rename_error_empty';
        }
        if ($newFilename === $oldFilename) {
            return 'mediaplace_rename_error_same';
        }
        if (strlen($newFilename) > 255) {
            return 'mediaplace_rename_error_long';
        }
        if (is_file(rex_path::media($newFilename)) || null !== rex_media::get($newFilename)) {
            return 'mediaplace_rename_error_exists';
        }
        if (null !== self::activeJobFor($oldFilename) || null !== self::activeJobFor($newFilename)) {
            return 'mediaplace_rename_error_busy';
        }

        return null;
    }

    /**
     * Benennt die Datei sofort um und legt den Job für die Verweise an.
     *
     * @throws RuntimeException mit i18n-Schlüssel als Nachricht
     */
    public static function start(string $oldFilename, string $newFilename, rex_user $user): int
    {
        self::ensureSchema();
        $media = rex_media::get($oldFilename);
        if (null === $media || !is_file(rex_path::media($oldFilename))) {
            throw new RuntimeException('mediaplace_rename_error_missing');
        }
        if (null !== ($error = self::validate($oldFilename, $newFilename))) {
            throw new RuntimeException($error);
        }

        // Neue Datei als Kopie: die alte bleibt bis zum Ende des Jobs erreichbar
        if (!copy(rex_path::media($oldFilename), rex_path::media($newFilename))) {
            throw new RuntimeException('mediaplace_rename_error_file');
        }
        @chmod(rex_path::media($newFilename), rex::getFilePerm());

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media'));
        $sql->setWhere(['filename' => $oldFilename]);
        $sql->setValue('filename', $newFilename);
        $sql->addGlobalUpdateFields($user->getLogin());
        $sql->update();

        rex_media_cache::delete($oldFilename);
        rex_media_cache::delete($newFilename);

        $state = ['tables' => self::scanTables(), 'ti' => 0, 'ci' => 0, 'after' => null, 'skip' => 0, 'articles' => []];
        $job = rex_sql::factory();
        $job->setTable(self::table());
        $job->setValue('old_filename', $oldFilename);
        $job->setValue('new_filename', $newFilename);
        $job->setValue('status', 'running');
        $job->setValue('state', json_encode($state));
        $job->setValue('createuser', $user->getLogin());
        $job->setValue('createdate', date('Y-m-d H:i:s'));
        $job->setValue('updatedate', date('Y-m-d H:i:s'));
        $job->insert();

        \rex_config::set('mediaplace', 'rename_pending', true);

        return (int) $job->getLastId();
    }

    /** Laufender Job, der diese Datei (alter oder neuer Name) betrifft */
    public static function activeJobFor(string $filename): ?int
    {
        if (!self::tableExists()) {
            return null;
        }
        $rows = rex_sql::factory()->getArray(
            'SELECT id FROM ' . self::table() . ' WHERE status = "running" AND (old_filename = ? OR new_filename = ?) LIMIT 1',
            [$filename, $filename],
        );

        return [] !== $rows ? (int) $rows[0]['id'] : null;
    }

    public static function hasPending(): bool
    {
        return (bool) \rex_config::get('mediaplace', 'rename_pending', false);
    }

    /**
     * @return array{id: int, old_filename: string, new_filename: string, status: string, replaced: int, error: string, tables_done: int, tables_total: int, table: string}|null
     */
    public static function status(int $id): ?array
    {
        if (!self::tableExists()) {
            return null;
        }
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE id = ?', [$id]);
        if ([] === $rows) {
            return null;
        }
        $row = $rows[0];
        $state = json_decode((string) $row['state'], true);
        $tables = is_array($state['tables'] ?? null) ? $state['tables'] : [];
        $ti = min((int) ($state['ti'] ?? 0), count($tables));

        return [
            'id' => (int) $row['id'],
            'old_filename' => (string) $row['old_filename'],
            'new_filename' => (string) $row['new_filename'],
            'status' => (string) $row['status'],
            'replaced' => (int) $row['replaced'],
            'error' => (string) $row['error'],
            'tables_done' => 'done' === $row['status'] ? count($tables) : $ti,
            'tables_total' => count($tables),
            'table' => (string) ($tables[$ti]['t'] ?? ''),
        ];
    }

    /** Fehlgeschlagenen Job an der Abbruchstelle fortsetzen */
    public static function retry(int $id): void
    {
        rex_sql::factory()->setQuery('UPDATE ' . self::table() . ' SET status = "running", error = NULL WHERE id = ? AND status = "failed"', [$id]);
        \rex_config::set('mediaplace', 'rename_pending', true);
    }

    /**
     * Arbeitet offene Jobs ab, bis die Zeit um ist. Läuft nur in einem Prozess gleichzeitig.
     */
    public static function work(float $seconds): void
    {
        if (!self::tableExists()) {
            return;
        }
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT GET_LOCK(?, 0) AS l', [self::LOCK]);
        if (1 !== (int) $sql->getValue('l')) {
            return;
        }
        try {
            $deadline = microtime(true) + $seconds;
            while (microtime(true) < $deadline) {
                $rows = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE status = "running" ORDER BY id LIMIT 1');
                if ([] === $rows) {
                    \rex_config::set('mediaplace', 'rename_pending', false);
                    break;
                }
                self::step($rows[0], $deadline);
            }
        } finally {
            rex_sql::factory()->setQuery('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    /**
     * JSON-Antwort sofort ausliefern und danach im selben Prozess weiterarbeiten (Browser darf geschlossen werden).
     *
     * @param array<string, mixed> $payload
     */
    public static function respondAndContinue(array $payload, float $seconds): never
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }
        ignore_user_abort(true);
        @set_time_limit((int) $seconds + 30);
        rex_response::cleanOutputBuffers();
        $json = (string) json_encode($payload);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
        header('Cache-Control: no-store');
        echo $json;
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        self::work($seconds);
        exit;
    }

    /**
     * Ersetzt den Dateinamen in einem gespeicherten Wert: nur als ganzer Name (nicht in "alt-bild.jpg"),
     * PHP-serialisierte Daten werden entpackt und mit korrekten Längen neu serialisiert.
     */
    public static function replaceIn(string $value, string $old, string $new): string
    {
        if (!str_contains($value, $old)) {
            return $value;
        }
        if (1 === preg_match('/^(a|s|i|b|d|N)[:;]/', $value)) {
            $data = @unserialize($value, ['allowed_classes' => false]);
            if (false !== $data || 'b:0;' === $value) {
                // Objekte nicht anfassen: ohne ihre Klasse lassen sie sich nicht sicher neu serialisieren
                return self::containsObject($data) ? $value : serialize(self::replaceDeep($data, $old, $new));
            }
        }

        return (string) preg_replace(self::pattern($old), str_replace(['\\', '$'], ['\\\\', '\\$'], $new), $value);
    }

    private static function pattern(string $old): string
    {
        return '/(?<![A-Za-z0-9_+\-.@])' . preg_quote($old, '/') . '(?![A-Za-z0-9_\-@])/';
    }

    private static function replaceDeep(mixed $data, string $old, string $new): mixed
    {
        if (is_string($data)) {
            return self::replaceIn($data, $old, $new);
        }
        if (!is_array($data)) {
            return $data;
        }
        $out = [];
        foreach ($data as $key => $value) {
            $key = is_string($key) ? self::replaceIn($key, $old, $new) : $key;
            $out[$key] = self::replaceDeep($value, $old, $new);
        }

        return $out;
    }

    private static function containsObject(mixed $data): bool
    {
        if (is_object($data)) {
            return true;
        }
        if (is_array($data)) {
            foreach ($data as $value) {
                if (self::containsObject($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * REDAXO-Tabellen mit Textspalten, ohne temporäre Tabellen, Protokolle und den eigenen Job.
     *
     * @return list<array{t: string, pk: list<string>, cols: list<string>}>
     */
    private static function scanTables(): array
    {
        $prefix = rex::getTablePrefix();
        $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix) . '%';
        $rows = rex_sql::factory()->getArray(
            'SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS c, c.DATA_TYPE AS type, c.COLUMN_KEY AS k
             FROM information_schema.COLUMNS c
             JOIN information_schema.TABLES tb ON tb.TABLE_SCHEMA = c.TABLE_SCHEMA AND tb.TABLE_NAME = c.TABLE_NAME
             WHERE c.TABLE_SCHEMA = DATABASE() AND tb.TABLE_TYPE = "BASE TABLE" AND c.TABLE_NAME LIKE ?
             ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION',
            [$like],
        );

        $tables = [];
        foreach ($rows as $row) {
            $name = (string) $row['t'];
            $tables[$name] ??= ['t' => $name, 'pk' => [], 'cols' => []];
            if ('PRI' === $row['k']) {
                $tables[$name]['pk'][] = (string) $row['c'];
            }
            // originalname ist der Name der hochgeladenen Datei und bleibt als Herkunft erhalten
            $skip = rex::getTable('media') === $name && 'originalname' === $row['c'];
            if (!$skip && in_array(strtolower((string) $row['type']), self::TEXT_TYPES, true)) {
                $tables[$name]['cols'][] = (string) $row['c'];
            }
        }

        $temp = $prefix . rex::getTempPrefix();
        $tables = array_values(array_filter($tables, static function (array $table) use ($temp): bool {
            return [] !== $table['cols']
                && !str_starts_with($table['t'], $temp)
                && !str_ends_with($table['t'], '_log')
                && $table['t'] !== self::table();
        }));

        return rex_extension::registerPoint(new rex_extension_point('MEDIAPLACE_RENAME_TABLES', $tables));
    }

    /**
     * @param array<string, mixed> $job
     */
    private static function step(array $job, float $deadline): void
    {
        $id = (int) $job['id'];
        $old = (string) $job['old_filename'];
        $new = (string) $job['new_filename'];
        $state = json_decode((string) $job['state'], true);
        $replaced = (int) $job['replaced'];

        try {
            while (microtime(true) < $deadline && $state['ti'] < count($state['tables'])) {
                $table = $state['tables'][$state['ti']];
                $more = [] !== $table['pk']
                    ? self::batchByKey($table, $old, $new, $state, $replaced)
                    : self::batchByValue($table, $old, $new, $state, $replaced);
                if (!$more) {
                    ++$state['ti'];
                    $state['ci'] = 0;
                    $state['after'] = null;
                    $state['skip'] = 0;
                }
                self::save($id, $state, $replaced);
            }
            if ($state['ti'] >= count($state['tables'])) {
                self::finish($id, $old, $new, $state, $replaced);
            }
        } catch (Throwable $e) {
            rex_logger::logException($e);
            rex_sql::factory()->setQuery(
                'UPDATE ' . self::table() . ' SET status = "failed", error = ?, state = ?, replaced = ?, updatedate = NOW() WHERE id = ?',
                [$e->getMessage(), json_encode($state), $replaced, $id],
            );
        }
    }

    /**
     * Ein Schritt für Tabellen mit Primärschlüssel, fortlaufend nach Schlüssel.
     *
     * @param array{t: string, pk: list<string>, cols: list<string>} $table
     * @param array<string, mixed> $state
     */
    private static function batchByKey(array $table, string $old, string $new, array &$state, int &$replaced): bool
    {
        $q = static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`';
        $pk = $table['pk'];
        $like = '%' . str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $old) . '%';

        $where = '(' . implode(' OR ', array_map(static fn (string $c): string => $q($c) . ' LIKE ?', $table['cols'])) . ')';
        $params = array_fill(0, count($table['cols']), $like);
        if (null !== $state['after']) {
            $where .= ' AND (' . implode(', ', array_map($q, $pk)) . ') > (' . implode(', ', array_fill(0, count($pk), '?')) . ')';
            array_push($params, ...$state['after']);
        }
        $articleCols = self::articleColumns($table['t']);
        $select = array_values(array_unique([...$pk, ...$table['cols'], ...$articleCols]));
        $rows = rex_sql::factory()->getArray(
            'SELECT ' . implode(', ', array_map($q, $select)) . ' FROM ' . $q($table['t']) . ' WHERE ' . $where
            . ' ORDER BY ' . implode(', ', array_map($q, $pk)) . ' LIMIT ' . self::BATCH,
            $params,
        );

        foreach ($rows as $row) {
            $changes = [];
            foreach ($table['cols'] as $col) {
                if (null === $row[$col]) {
                    continue;
                }
                $value = (string) $row[$col];
                $updated = self::replaceIn($value, $old, $new);
                if ($updated !== $value) {
                    $changes[$col] = $updated;
                }
            }
            if ([] !== $changes) {
                $update = rex_sql::factory();
                $update->setTable($table['t']);
                $update->setWhere(array_combine($pk, array_map(static fn (string $c): string => (string) $row[$c], $pk)));
                $update->setValues($changes);
                $update->update();
                $replaced += count($changes);
                if ([] !== $articleCols) {
                    $state['articles'][(int) $row[$articleCols[0]] . '-' . (int) $row[$articleCols[1]]] = true;
                }
                if (rex::getTable('config') === $table['t']) {
                    $state['config'] = true;
                }
            }
            $state['after'] = array_map(static fn (string $c) => $row[$c], $pk);
        }

        return count($rows) === self::BATCH;
    }

    /**
     * Ein Schritt für Tabellen ohne Primärschlüssel: je Spalte über die betroffenen Werte.
     *
     * @param array{t: string, pk: list<string>, cols: list<string>} $table
     * @param array<string, mixed> $state
     */
    private static function batchByValue(array $table, string $old, string $new, array &$state, int &$replaced): bool
    {
        $q = static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`';
        $col = $table['cols'][$state['ci']] ?? null;
        if (null === $col) {
            return false;
        }
        $like = '%' . str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $old) . '%';
        $rows = rex_sql::factory()->getArray(
            'SELECT DISTINCT ' . $q($col) . ' AS v FROM ' . $q($table['t']) . ' WHERE ' . $q($col) . ' LIKE ? ORDER BY 1 LIMIT ' . self::BATCH . ' OFFSET ' . (int) $state['skip'],
            [$like],
        );
        foreach ($rows as $row) {
            $value = (string) $row['v'];
            $updated = self::replaceIn($value, $old, $new);
            if ($updated === $value) {
                ++$state['skip'];
                continue;
            }
            $update = rex_sql::factory();
            $update->setQuery('UPDATE ' . $q($table['t']) . ' SET ' . $q($col) . ' = ? WHERE BINARY ' . $q($col) . ' = BINARY ?', [$updated, $value]);
            $replaced += $update->getRows();
        }
        if (count($rows) < self::BATCH) {
            ++$state['ci'];
            $state['skip'] = 0;
        }

        return $state['ci'] < count($table['cols']);
    }

    /** @return list<string> Spalten für Artikel-ID und Sprache, wenn der Artikel-Cache betroffen ist */
    private static function articleColumns(string $table): array
    {
        return match ($table) {
            rex::getTable('article_slice') => ['article_id', 'clang_id'],
            rex::getTable('article') => ['id', 'clang_id'],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function save(int $id, array $state, int $replaced): void
    {
        rex_sql::factory()->setQuery(
            'UPDATE ' . self::table() . ' SET state = ?, replaced = ?, updatedate = NOW() WHERE id = ?',
            [json_encode($state), $replaced, $id],
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function finish(int $id, string $old, string $new, array $state, int $replaced): void
    {
        // Übergangskopie löschen, außer der alte Name ist inzwischen wieder vergeben
        if (null === rex_media::get($old) && is_file(rex_path::media($old))) {
            @unlink(rex_path::media($old));
        }
        rex_media_manager::deleteCache($old);
        rex_media_cache::delete($old);
        rex_media_cache::delete($new);
        if (class_exists(rex_article_cache::class)) {
            foreach (array_keys($state['articles']) as $key) {
                [$articleId, $clangId] = array_map('intval', explode('-', (string) $key));
                rex_article_cache::delete($articleId, $clangId);
            }
        }

        // rex_config wird aus einem Cache gelesen: nach direkten Änderungen neu laden (eigene Änderungen vorher sichern)
        if (!empty($state['config'])) {
            \rex_config::save();
            \rex_config::refresh();
        }

        rex_sql::factory()->setQuery(
            'UPDATE ' . self::table() . ' SET status = "done", state = ?, replaced = ?, updatedate = NOW() WHERE id = ?',
            [json_encode($state), $replaced, $id],
        );
        rex_extension::registerPoint(new rex_extension_point('MEDIAPLACE_MEDIA_RENAMED', $new, [
            'old_filename' => $old,
            'new_filename' => $new,
            'replaced' => $replaced,
        ]));
    }

    private static function tableExists(): bool
    {
        return rex_sql_table::get(self::table())->exists();
    }
}
