<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Central tag storage for mediaplace demo.
 * Tags are stored system-wide and mapped to media files separately.
 */
class SystemTagManager
{
    public const COLLECTION_PREFIX = 'collection:';

    public static function ensureSchema(): void
    {
        \rex_sql_table::get(\rex::getTable('mediaplace_tags'))
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new \rex_sql_column('name', 'varchar(190)', true))
            ->ensureColumn(new \rex_sql_column('color', 'varchar(7)', true, '#4a90d9'))
            // Geschlossenes Vokabular fuer KI-Auto-Tagging-Vorschlaege (siehe
            // AiAutoTagService): NUR explizit hier freigegebene Tags landen
            // im Prompt -- die KI erfindet nie neue Tags, sie waehlt aus
            // einer vom Menschen kuratierten Liste. Default 0 (opt-in), damit
            // ein bestehender Tag-Katalog nicht ungefragt komplett KI-nutzbar
            // wird, sobald das Feature aktiviert wird.
            ->ensureColumn(new \rex_sql_column('ai_allowed', 'tinyint(1)', false, '0'))
            ->ensureColumn(new \rex_sql_column('create_user', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('create_date', 'datetime'))
            ->ensureColumn(new \rex_sql_column('update_user', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('update_date', 'datetime'))
            ->ensureGlobalColumns()
            ->ensure();

        \rex_sql_table::get(\rex::getTable('mediaplace_media_tags'))
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new \rex_sql_column('filename', 'varchar(255)', true))
            ->ensureColumn(new \rex_sql_column('tag_name', 'varchar(190)', true))
            ->ensureColumn(new \rex_sql_column('priority', 'int(10) unsigned', false, '0'))
            ->ensureColumn(new \rex_sql_column('create_user', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('create_date', 'datetime'))
            ->ensureColumn(new \rex_sql_column('update_user', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('update_date', 'datetime'))
            ->ensureGlobalColumns()
            ->ensure();
    }

    /**
     * @return array<int, array{name:string,color:string,ai_allowed:bool}>
     */
    public static function getCatalog(): array
    {
        self::ensureSchema();

        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT name, color, ai_allowed FROM ' . \rex::getTable('mediaplace_tags') . ' ORDER BY name ASC',
        );

        $out = [];
        foreach ($rows as $row) {
            $name = self::normalizeName((string) ($row['name'] ?? ''));
            if ('' === $name) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'color' => self::normalizeColor((string) ($row['color'] ?? '')),
                'ai_allowed' => (bool) ($row['ai_allowed'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Geschlossenes Vokabular fuer AiAutoTagService::suggestTags() -- nur
     * Tags, die in der Tag-Verwaltung explizit als "Für KI-Vorschläge
     * freigeben" markiert wurden. Sammlungen sind hier grundsaetzlich
     * ausgeschlossen (getTags() filtert sie bereits), unabhaengig vom
     * ai_allowed-Wert einer Sammlung -- Sammlungen sind kein Content-Tag.
     *
     * @return list<string>
     */
    public static function getAiAllowedTagNames(): array
    {
        $names = [];
        foreach (self::getTags() as $tag) {
            if ($tag['ai_allowed']) {
                $names[] = $tag['name'];
            }
        }

        return $names;
    }

    /**
     * Schaltet die KI-Freigabe eines bestehenden Tags um (Tag-Verwaltung).
     * Kein-Op fuer unbekannte/Sammlungs-Namen.
     */
    public static function setAiAllowed(string $name, bool $allowed): bool
    {
        self::ensureSchema();

        $name = self::normalizeName($name);
        if ('' === $name || self::isCollectionTagName($name)) {
            return false;
        }

        $sql = \rex_sql::factory();
        $existing = $sql->getArray(
            'SELECT id FROM ' . \rex::getTable('mediaplace_tags') . ' WHERE name = :name',
            ['name' => $name],
        );
        if ([] === $existing) {
            return false;
        }

        $write = \rex_sql::factory();
        $write->setTable(\rex::getTable('mediaplace_tags'));
        $write->setWhere('name = :name', ['name' => $name]);
        $write->setValue('ai_allowed', $allowed ? 1 : 0);
        $write->setValue('update_user', \rex::getUser()?->getLogin() ?? 'system');
        $write->setValue('update_date', date('Y-m-d H:i:s'));
        $write->update();

        return true;
    }

    /**
     * @return array<int, array{name:string,color:string}>
     */
    public static function getTagsForFilename(string $filename): array
    {
        self::ensureSchema();

        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT mt.tag_name AS name, COALESCE(t.color, "#4a90d9") AS color
             FROM ' . \rex::getTable('mediaplace_media_tags') . ' mt
             LEFT JOIN ' . \rex::getTable('mediaplace_tags') . ' t ON t.name = mt.tag_name
             WHERE mt.filename = :filename
             ORDER BY mt.priority, mt.id',
            ['filename' => $filename],
        );

        $out = [];
        foreach ($rows as $row) {
            $name = self::normalizeName((string) ($row['name'] ?? ''));
            if ('' === $name) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'color' => self::normalizeColor((string) ($row['color'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, string> $filenames
     * @return array<string, array<int, array{name:string,color:string}>>
     */
    public static function getTagsForFilenames(array $filenames): array
    {
        self::ensureSchema();

        $normalizedFiles = [];
        foreach ($filenames as $filename) {
            $f = trim((string) $filename);
            if ('' === $f || isset($normalizedFiles[$f])) {
                continue;
            }
            $normalizedFiles[$f] = true;
        }

        if ([] === $normalizedFiles) {
            return [];
        }

        $fileList = array_keys($normalizedFiles);
        $placeholders = implode(', ', array_fill(0, count($fileList), '?'));

        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT mt.filename, mt.tag_name AS name, COALESCE(t.color, "#4a90d9") AS color
             FROM ' . \rex::getTable('mediaplace_media_tags') . ' mt
             LEFT JOIN ' . \rex::getTable('mediaplace_tags') . ' t ON t.name = mt.tag_name
             WHERE mt.filename IN (' . $placeholders . ')
             ORDER BY mt.filename, mt.priority, mt.id',
            $fileList,
        );

        $map = [];
        foreach ($fileList as $filename) {
            $map[$filename] = [];
        }

        foreach ($rows as $row) {
            $filename = (string) ($row['filename'] ?? '');
            if ('' === $filename) {
                continue;
            }

            $name = self::normalizeName((string) ($row['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $map[$filename][] = [
                'name' => $name,
                'color' => self::normalizeColor((string) ($row['color'] ?? '')),
            ];
        }

        return $map;
    }

    /**
     * Alle Dateien mit einem bestimmten Tag (exakter Name).
     *
     * @return list<string>
     */
    public static function getFilenamesForTag(string $tagName): array
    {
        self::ensureSchema();

        $name = self::normalizeName($tagName);
        if ('' === $name) {
            return [];
        }

        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT filename FROM ' . \rex::getTable('mediaplace_media_tags') . ' WHERE tag_name = :tag ORDER BY filename',
            ['tag' => $name],
        );

        $filenames = [];
        foreach ($rows as $row) {
            $filename = trim((string) ($row['filename'] ?? ''));
            if ('' !== $filename) {
                $filenames[] = $filename;
            }
        }

        return $filenames;
    }

    /**
     * Alle Dateien einer Sammlung. Sammlungen sind Tags mit "collection:"-Praefix
     * (siehe COLLECTION_PREFIX) -- der Praefix wird hier automatisch ergaenzt.
     *
     * @return list<string>
     */
    public static function getFilenamesForCollection(string $collectionName): array
    {
        $name = trim($collectionName);
        if ('' === $name) {
            return [];
        }

        return self::getFilenamesForTag(self::COLLECTION_PREFIX . $name);
    }

    public static function isCollectionTagName(string $tagName): bool
    {
        return 0 === stripos(trim($tagName), self::COLLECTION_PREFIX);
    }

    /**
     * Katalog ohne Sammlungen (siehe getCollections() fuer die Gegenseite).
     *
     * @return array<int, array{name:string,color:string}>
     */
    public static function getTags(): array
    {
        return array_values(array_filter(
            self::getCatalog(),
            static fn (array $tag): bool => !self::isCollectionTagName($tag['name']),
        ));
    }

    /**
     * Sammlungsnamen aus dem Katalog, ohne "collection:"-Praefix.
     *
     * @return array<int, array{name:string,color:string}>
     */
    public static function getCollections(): array
    {
        $out = [];
        foreach (self::getCatalog() as $tag) {
            if (!self::isCollectionTagName($tag['name'])) {
                continue;
            }
            $out[] = [
                'name' => trim(mb_substr($tag['name'], mb_strlen(self::COLLECTION_PREFIX))),
                'color' => $tag['color'],
            ];
        }
        return $out;
    }

    /**
     * Dateianzahl pro Sammlung UEBER DEN GESAMTEN Medienpool (nicht nur die
     * aktuell im Client geladene Kategorie/Ansicht) -- der Sidebar-Zaehler
     * war seit MediaPlace 1.0.0 fälschlich auf lastLoadedFiles beschraenkt
     * (siehe getCollectionsForCurrentCategory() im Client), zeigte je nach
     * Kategorie also 0 statt der echten Mitgliederzahl.
     *
     * $accessibleCategoryIds MUSS von MediaPermission::getAccessibleCategoryIds()
     * kommen -- ohne diesen Filter wuerden Zaehler auch Dateien in fuer den
     * User gesperrten Kategorien mitzaehlen.
     *
     * @param list<int> $accessibleCategoryIds
     * @return array<string, int> Sammlungsname (ohne Praefix) -> Dateianzahl
     */
    public static function getCollectionCounts(array $accessibleCategoryIds): array
    {
        self::ensureSchema();

        if ([] === $accessibleCategoryIds) {
            return [];
        }

        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($accessibleCategoryIds), '?'));
        $rows = $sql->getArray(
            'SELECT mt.tag_name AS tag_name, COUNT(DISTINCT mt.filename) AS cnt
             FROM ' . \rex::getTable('mediaplace_media_tags') . ' mt
             INNER JOIN ' . \rex::getTable('media') . ' m ON m.filename = mt.filename
             WHERE mt.tag_name LIKE ? AND m.category_id IN (' . $placeholders . ')
             GROUP BY mt.tag_name',
            array_merge([self::COLLECTION_PREFIX . '%'], $accessibleCategoryIds),
        );

        $counts = [];
        foreach ($rows as $row) {
            $tagName = (string) ($row['tag_name'] ?? '');
            if (!self::isCollectionTagName($tagName)) {
                continue;
            }
            $name = trim(mb_substr($tagName, mb_strlen(self::COLLECTION_PREFIX)));
            if ('' === $name) {
                continue;
            }
            $counts[$name] = (int) ($row['cnt'] ?? 0);
        }

        return $counts;
    }

    /**
     * Dateianzahl pro (echtem, nicht-Sammlungs-)Tag UEBER DEN GESAMTEN
     * Medienpool -- gleiches Muster wie getCollectionCounts() oben, nur ohne
     * COLLECTION_PREFIX-Filter. Sidebar-Tag-Filter zeigt darueber nur Tags
     * an, die tatsaechlich mindestens einer (fuer den User zugaenglichen)
     * Datei zugewiesen sind, statt des kompletten Katalogs inklusive nie
     * benutzter/verwaister Tags.
     *
     * $accessibleCategoryIds MUSS von MediaPermission::getAccessibleCategoryIds()
     * kommen, siehe getCollectionCounts().
     *
     * @param list<int> $accessibleCategoryIds
     * @return array<string, int> Tag-Name -> Dateianzahl
     */
    public static function getTagCounts(array $accessibleCategoryIds): array
    {
        self::ensureSchema();

        if ([] === $accessibleCategoryIds) {
            return [];
        }

        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($accessibleCategoryIds), '?'));
        $rows = $sql->getArray(
            'SELECT mt.tag_name AS tag_name, COUNT(DISTINCT mt.filename) AS cnt
             FROM ' . \rex::getTable('mediaplace_media_tags') . ' mt
             INNER JOIN ' . \rex::getTable('media') . ' m ON m.filename = mt.filename
             WHERE m.category_id IN (' . $placeholders . ')
             GROUP BY mt.tag_name',
            $accessibleCategoryIds,
        );

        $counts = [];
        foreach ($rows as $row) {
            $tagName = (string) ($row['tag_name'] ?? '');
            if ('' === $tagName || self::isCollectionTagName($tagName)) {
                continue;
            }
            $counts[$tagName] = (int) ($row['cnt'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param array<int, array{name?:string,color?:string}|string> $tags
     */
    public static function saveTagsForFilename(string $filename, array $tags): void
    {
        self::ensureSchema();

        $normalized = [];
        foreach ($tags as $index => $tag) {
            $name = '';
            $color = '#4a90d9';

            if (is_array($tag)) {
                $name = self::normalizeName((string) ($tag['name'] ?? ''));
                $color = self::normalizeColor((string) ($tag['color'] ?? ''));
            } elseif (is_string($tag)) {
                $name = self::normalizeName($tag);
            }

            if ('' === $name || isset($normalized[$name])) {
                continue;
            }

            $normalized[$name] = [
                'name' => $name,
                'color' => $color,
                'priority' => (int) $index,
            ];
        }

        $sql = \rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . \rex::getTable('mediaplace_media_tags') . ' WHERE filename = :filename',
            ['filename' => $filename],
        );

        $user = \rex::getUser()?->getLogin() ?? 'system';
        $now = date('Y-m-d H:i:s');

        foreach ($normalized as $row) {
            $upsertSql = \rex_sql::factory();
            $existing = $upsertSql->getArray(
                'SELECT id FROM ' . \rex::getTable('mediaplace_tags') . ' WHERE name = :name',
                ['name' => $row['name']],
            );

            if ([] !== $existing) {
                $upsertSql->setTable(\rex::getTable('mediaplace_tags'));
                $upsertSql->setWhere('name = :name', ['name' => $row['name']]);
                $upsertSql->setValue('color', $row['color']);
                $upsertSql->setValue('update_user', $user);
                $upsertSql->setValue('update_date', $now);
                $upsertSql->update();
            } else {
                $upsertSql->setTable(\rex::getTable('mediaplace_tags'));
                $upsertSql->setValue('name', $row['name']);
                $upsertSql->setValue('color', $row['color']);
                $upsertSql->setValue('create_user', $user);
                $upsertSql->setValue('create_date', $now);
                $upsertSql->insert();
            }

            $mapSql = \rex_sql::factory();
            $mapSql->setTable(\rex::getTable('mediaplace_media_tags'));
            $mapSql->setValue('filename', $filename);
            $mapSql->setValue('tag_name', $row['name']);
            $mapSql->setValue('priority', $row['priority']);
            $mapSql->setValue('create_user', $user);
            $mapSql->setValue('create_date', $now);
            $mapSql->insert();
        }
    }

    public static function ensureCatalogTag(string $name, string $color = '#4a90d9'): bool
    {
        self::ensureSchema();

        $name = self::normalizeName($name);
        if ('' === $name) {
            return false;
        }

        $color = self::normalizeColor($color);
        $user = \rex::getUser()?->getLogin() ?? 'system';
        $now = date('Y-m-d H:i:s');

        $sql = \rex_sql::factory();
        $existing = $sql->getArray(
            'SELECT id FROM ' . \rex::getTable('mediaplace_tags') . ' WHERE name = :name',
            ['name' => $name],
        );

        $write = \rex_sql::factory();
        $write->setTable(\rex::getTable('mediaplace_tags'));
        if ([] !== $existing) {
            $write->setWhere('name = :name', ['name' => $name]);
            $write->setValue('color', $color);
            $write->setValue('update_user', $user);
            $write->setValue('update_date', $now);
            $write->update();
        } else {
            $write->setValue('name', $name);
            $write->setValue('color', $color);
            $write->setValue('create_user', $user);
            $write->setValue('create_date', $now);
            $write->insert();
        }

        return true;
    }

    public static function renameCatalogTag(string $oldName, string $newName): int
    {
        self::ensureSchema();

        $oldName = self::normalizeName($oldName);
        $newName = self::normalizeName($newName);
        if ('' === $oldName || '' === $newName || $oldName === $newName) {
            return 0;
        }

        $user = \rex::getUser()?->getLogin() ?? 'system';
        $now = date('Y-m-d H:i:s');

        $sql = \rex_sql::factory();
        $target = $sql->getArray(
            'SELECT id FROM ' . \rex::getTable('mediaplace_tags') . ' WHERE name = :name',
            ['name' => $newName],
        );

        if ([] === $target) {
            // ai_allowed wird mitkopiert -- ein Umbenennen soll die
            // KI-Freigabe eines Tags nicht stillschweigend zuruecksetzen.
            $copySql = \rex_sql::factory();
            $copySql->setQuery(
                'INSERT INTO ' . \rex::getTable('mediaplace_tags') . ' (name, color, ai_allowed, create_user, create_date)
                 SELECT :new_name, color, ai_allowed, :user, :now
                 FROM ' . \rex::getTable('mediaplace_tags') . '
                 WHERE name = :old_name',
                [
                    'new_name' => $newName,
                    'user' => $user,
                    'now' => $now,
                    'old_name' => $oldName,
                ],
            );
        }

        $mapSql = \rex_sql::factory();
        $mapSql->setQuery(
            'UPDATE ' . \rex::getTable('mediaplace_media_tags') . '
             SET tag_name = :new_name, update_user = :user, update_date = :now
             WHERE tag_name = :old_name',
            [
                'new_name' => $newName,
                'old_name' => $oldName,
                'user' => $user,
                'now' => $now,
            ],
        );

        $cleanupSql = \rex_sql::factory();
        $cleanupSql->setQuery(
            'DELETE t1
             FROM ' . \rex::getTable('mediaplace_media_tags') . ' t1
             INNER JOIN ' . \rex::getTable('mediaplace_media_tags') . ' t2
               ON t1.filename = t2.filename
              AND t1.tag_name = t2.tag_name
              AND t1.id > t2.id',
        );

        $deleteOld = \rex_sql::factory();
        $deleteOld->setQuery(
            'DELETE FROM ' . \rex::getTable('mediaplace_tags') . ' WHERE name = :old_name',
            ['old_name' => $oldName],
        );

        return (int) $mapSql->getRows();
    }

    public static function deleteCatalogTag(string $name): int
    {
        self::ensureSchema();

        $name = self::normalizeName($name);
        if ('' === $name) {
            return 0;
        }

        $mapSql = \rex_sql::factory();
        $mapSql->setQuery(
            'DELETE FROM ' . \rex::getTable('mediaplace_media_tags') . ' WHERE tag_name = :name',
            ['name' => $name],
        );

        $tagSql = \rex_sql::factory();
        $tagSql->setQuery(
            'DELETE FROM ' . \rex::getTable('mediaplace_tags') . ' WHERE name = :name',
            ['name' => $name],
        );

        return (int) $mapSql->getRows();
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        return mb_substr($name, 0, 120);
    }

    private static function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return '#4a90d9';
        }
        return strtolower($color);
    }
}
