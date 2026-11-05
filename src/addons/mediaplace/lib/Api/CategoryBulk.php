<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Massenaktionen fuer ALLE Dateien einer Kategorie (nicht rekursiv in
 * Unterkategorien) -- Ergaenzung zur Checkbox-Mehrfachauswahl, die bei sehr
 * vielen Dateien weder als UI (X-tausend Checkboxen) noch als Performance
 * (jede Datei muss erst geladen sein, siehe getVisibleFilenames() in
 * modules/multiselect.js) skaliert. Ausgangspunkt ist deshalb das Kategorie-
 * Kontextmenue (openCatMenu() in modules/categories.js), nicht eine
 * Datei-Auswahl im Grid.
 *
 * POST (JSON body, {action: ...}):
 * - count              {category_id} -> Gesamtanzahl Dateien in der Kategorie
 * - move_batch         {category_id, target_category_id, limit} -> verschiebt
 *   bis zu `limit` Dateien ueber rex_media_service::updateMedia() (feuert
 *   MEDIA_UPDATED wie ein normales Einzel-Update), gibt succeeded/remaining/
 *   errors zurueck -- der Client ruft wiederholt auf, bis entweder remaining=0
 *   ODER ein Batch succeeded=0 hatte (Chunking gegen PHP max_execution_time
 *   bei sehr grossen Kategorien; succeeded statt der reinen Batch-Groesse als
 *   Fortschrittsmass ist wichtig, siehe Kommentar bei handleMoveBatch()).
 * - delete_batch       {category_id, limit} -> loescht bis zu `limit` Dateien
 *   ueber rex_media_service::deleteMedia() (Dateisystem+Cache+MEDIA_DELETED
 *   wie Einzel-Loeschen), sammelt Fehler pro Datei statt beim ersten Fehler
 *   abzubrechen (z.B. eine einzelne "in Benutzung"-gesperrte Datei).
 * - add_to_collection  {category_id, collection_name} -> ein einziger
 *   Bulk-INSERT (kein Chunking noetig, kein Medien-Service-Aufruf pro Datei
 *   noetig -- eine Sammlungs-Zuordnung ist nur eine Tag-Zeile, kein
 *   Medien-Update mit Dateisystem-/Cache-Beruehrung).
 * - add_tag            {category_id, tag_name} -> wie add_to_collection,
 *   nur ohne "collection:"-Praefix (normaler System-Tag statt Sammlung),
 *   siehe handleAddTag().
 */
class CategoryBulk extends rex_api_function
{
    // Siehe Api\MediaList::$published-Kommentar: umgeht nur das
    // isBackend()-Gate, execute() prueft Login+Rechte ohnehin selbst.
    protected $published = true;

    private const BATCH_LIMIT_DEFAULT = 100;
    private const BATCH_LIMIT_MAX = 500;

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        // Eigene, engere Berechtigung zusaetzlich zur normalen Kategorie-
        // Zugriffspruefung unten -- Massenaktionen sind ein deutlich groesseres
        // Blast-Radius-Risiko als einzelne Datei-Operationen, siehe
        // MediaPermission::hasBulkOperationsAccess().
        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasBulkOperationsAccess()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = [];
        }

        $action = (string) ($body['action'] ?? '');
        $categoryId = (int) ($body['category_id'] ?? -1);

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($categoryId)) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        switch ($action) {
            case 'count':
                $this->handleCount($categoryId);
                break;
            case 'move_batch':
                $this->handleMoveBatch($categoryId, $body);
                break;
            case 'delete_batch':
                $this->handleDeleteBatch($categoryId, $body);
                break;
            case 'add_to_collection':
                $this->handleAddTag($categoryId, $body, \FriendsOfRedaxo\Mediaplace\SystemTagManager::COLLECTION_PREFIX);
                break;
            case 'add_tag':
                $this->handleAddTag($categoryId, $body, '');
                break;
            default:
                \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                \rex_response::sendJson(['error' => 'Unknown action']);
        }

        exit;
    }

    private function handleCount(int $categoryId): void
    {
        \rex_response::sendJson(['total' => $this->countInCategory($categoryId)]);
    }

    private function countInCategory(int $categoryId): int
    {
        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT COUNT(*) AS c FROM ' . \rex::getTable('media') . ' WHERE category_id = :cat',
            ['cat' => $categoryId],
        );
        return (int) ($rows[0]['c'] ?? 0);
    }

    /**
     * @return list<string>
     */
    private function fetchBatchFilenames(int $categoryId, int $limit): array
    {
        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT filename FROM ' . \rex::getTable('media') . ' WHERE category_id = :cat ORDER BY filename LIMIT ' . $limit,
            ['cat' => $categoryId],
        );
        return array_map(static fn (array $r): string => (string) $r['filename'], $rows);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function normalizeLimit(array $body): int
    {
        $limit = (int) ($body['limit'] ?? self::BATCH_LIMIT_DEFAULT);
        return max(1, min(self::BATCH_LIMIT_MAX, $limit));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handleMoveBatch(int $categoryId, array $body): void
    {
        $targetCategoryId = (int) ($body['target_category_id'] ?? -1);
        if ($targetCategoryId === $categoryId) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Target equals source']);
            return;
        }
        if ($targetCategoryId > 0 && !\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($targetCategoryId)) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied for target category']);
            return;
        }

        $filenames = $this->fetchBatchFilenames($categoryId, $this->normalizeLimit($body));

        $succeeded = 0;
        $errors = [];
        foreach ($filenames as $filename) {
            try {
                // updateMedia() erwartet ein vollstaendiges Datenset wie ein
                // klassisches Bearbeiten-Formular (greift z.B. direkt auf
                // $data['title'] zu, ohne Fallback) -- kein Partial-Update.
                // Aktuellen Titel deshalb mitschicken, sonst PHP-Warning/
                // gespeicherter Titel-Datenverlust.
                $media = \rex_media::get($filename);
                if (!$media) {
                    // Gleiche Formulierung wie REDAXOs eigene Meldungen (siehe
                    // deleteMedia()-Exception unten): Dateiname steckt bereits
                    // im Text, damit der Client keinen eigenen, doppelten
                    // Dateiname-Vorspann rendern muss.
                    $errors[] = ['filename' => $filename, 'message' => 'File "' . \rex_escape($filename) . '" not found.'];
                    continue;
                }
                \rex_media_service::updateMedia($filename, [
                    'title' => $media->getTitle(),
                    'category_id' => $targetCategoryId,
                ]);
                ++$succeeded;
            } catch (\Exception $e) {
                $errors[] = ['filename' => $filename, 'message' => $e->getMessage()];
            }
        }

        \rex_response::sendJson([
            // "succeeded" (nicht die Batch-Groesse) ist der eigentliche
            // Fortschritt -- der Client bricht ab, sobald ein Batch 0
            // erfolgreiche Operationen hatte, siehe runChunkedBulkAction()
            // in modules/categories.js. Mit der reinen Batch-Groesse als
            // "processed" wuerden dauerhaft gesperrte Dateien (z.B. "in
            // Benutzung") jeden Batch erneut mitgezaehlt, obwohl nichts
            // passiert -- Endlosschleife, tatsaechlich live aufgetreten.
            'succeeded' => $succeeded,
            'remaining' => $this->countInCategory($categoryId),
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handleDeleteBatch(int $categoryId, array $body): void
    {
        $filenames = $this->fetchBatchFilenames($categoryId, $this->normalizeLimit($body));

        $succeeded = 0;
        $errors = [];
        foreach ($filenames as $filename) {
            try {
                \rex_media_service::deleteMedia($filename);
                ++$succeeded;
            } catch (\Exception $e) {
                // $e->getMessage() ist bei "in Benutzung" bereits fertiges,
                // von REDAXO selbst erzeugtes HTML inkl. Links zu den
                // referenzierenden Objekten (rex_mediapool::mediaIsInUse()) --
                // wird clientseitig bewusst als HTML gerendert, nicht als
                // Text (siehe showBulkProgressModal()).
                $errors[] = ['filename' => $filename, 'message' => $e->getMessage()];
            }
        }

        \rex_response::sendJson([
            'succeeded' => $succeeded,
            'remaining' => $this->countInCategory($categoryId),
            'errors' => $errors,
        ]);
    }

    /**
     * Gemeinsame Implementierung fuer add_to_collection (prefix =
     * SystemTagManager::COLLECTION_PREFIX) und add_tag (prefix = '', ganz
     * normaler System-Tag) -- beide sind technisch identisch (eine Zeile in
     * mediaplace_media_tags), nur die Sammlungs-Variante braucht den
     * "collection:"-Praefix, der Sammlungen von normalen Tags unterscheidet.
     *
     * @param array<string, mixed> $body
     */
    private function handleAddTag(int $categoryId, array $body, string $prefix): void
    {
        // Gleiche Normalisierung wie SystemTagManager::normalizeName() (private,
        // deshalb hier gespiegelt) -- ensureCatalogTag() normalisiert intern
        // selbst, gibt den normalisierten Namen aber nicht zurueck. Ohne diese
        // Spiegelung koennte der Katalog-Eintrag (normalisiert) vom tag_name
        // in den Datei-Zuordnungszeilen (unnormalisiert) abweichen und der
        // Join zwischen beiden Tabellen wuerde brechen.
        $rawName = trim((string) ($body['tag_name'] ?? $body['collection_name'] ?? ''));
        $rawName = preg_replace('/\s+/u', ' ', $rawName) ?? '';
        $rawName = mb_substr($rawName, 0, 120);
        if ('' === $rawName) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Missing tag_name']);
            return;
        }

        $tagName = $prefix . $rawName;

        \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureSchema();
        \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureCatalogTag($tagName);

        $user = \rex::getUser()?->getLogin() ?? 'system';
        $now = date('Y-m-d H:i:s');

        $sql = \rex_sql::factory();
        $sql->setQuery(
            'INSERT INTO ' . \rex::getTable('mediaplace_media_tags') . ' (filename, tag_name, priority, create_user, create_date)
             SELECT m.filename, :tag, 0, :user, :now
             FROM ' . \rex::getTable('media') . ' m
             WHERE m.category_id = :cat
               AND NOT EXISTS (
                   SELECT 1 FROM ' . \rex::getTable('mediaplace_media_tags') . ' t
                   WHERE t.filename = m.filename AND t.tag_name = :tag2
               )',
            ['tag' => $tagName, 'user' => $user, 'now' => $now, 'cat' => $categoryId, 'tag2' => $tagName],
        );

        \rex_response::sendJson(['affected' => $sql->getRows()]);
    }
}
