<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Uebergangs-Fallback fuer die Medienliste, solange die installierte Version
 * von FriendsOfRedaxo/api media/list (handleMediaList()) noch nicht nach
 * Kategorie-Rechten filtert (behoben ab api 1.3.1, siehe dortiges Changelog
 * und PR https://github.com/FriendsOfREDAXO/api/pull/78). Bis dahin bekam
 * ein Backend-User mit auf einzelne Kategorien eingeschraenkten Medienrechten
 * ueber die normale Medienliste trotzdem saemtliche Dateien aller Kategorien
 * zurueck -- dieser Endpunkt reimplementiert dieselbe Query (Kategorie-Filter,
 * Freitextsuche, Pagination) mit korrekter Rechtepruefung direkt in der SQL,
 * damit die Pagination trotz Filterung stimmt (kein Nachfiltern auf bereits
 * geladenen Seiten, das wuerde die Trefferzahl pro Seite verfaelschen).
 *
 * Wird nur genutzt, wenn boot.php (OUTPUT_FILTER) die installierte api-Version
 * als zu alt erkennt (data-api-media-list-secure="0" am #mp-root); mediaplace.js
 * schickt den Request dann hierher statt an /api/backend/media. Sobald api
 * ueberall in Version >=1.3.1 installiert ist, kann diese Datei inklusive
 * boot.php-Weiche und dem JS-Umschalter in mediaplace-api.js wieder entfernt
 * werden -- RUECKBAU-TODO, kein dauerhafter Bestandteil von MediaPlace.
 *
 * Unterstuetzt bewusst nur die Parameter, die mediaplace.js tatsaechlich
 * schickt (buildMediaEndpoint()/fetchTypeCounts()): filter[category_id],
 * filter[term], filter[types], filter[collection], filter[alt_missing],
 * filter[tags], page, per_page. Freitextsuche spiegelt dieselbe Semantik wie
 * api's Media.php (Anfuehrungszeichen gruppieren, "type:jpg,png" filtert die
 * Dateiendung), filter[types] ebenso (extensionExpression() IN (...)), damit
 * sich das Verhalten fuer den Nutzer durch den Fallback nicht aendert.
 *
 * GET /api/backend/mediaplace_media_list?filter[category_id]=&filter[term]=&page=&per_page=
 */
class MediaList extends rex_api_function
{
    // rex_api_function::$published ist standardmaessig false, dann darf der
    // Aufruf nur greifen, wenn rex::isBackend() zum Zeitpunkt des Requests
    // wahr ist -- das haengt am tatsaechlichen Einstiegspunkt (public/index.php
    // setzt REDAXO=false, public/redaxo/index.php setzt REDAXO=true) und damit
    // an der konkreten URL, unter der die aufrufende Seite erreichbar ist.
    // published=true umgeht diese Gate-Pruefung bewusst -- execute() prueft
    // rex::getUser() + MediaPermission::hasMediaAccess() ohnehin selbst als
    // eigentliche Absicherung, das ist keine Sicherheitslockerung.
    protected $published = true;

    private const MAX_PER_PAGE = 1000;

    private const MEDIA_FIELDS = ['filename', 'category_id', 'filetype', 'originalname', 'filesize', 'width', 'height', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasMediaAccess()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $filter = rex_request('filter', 'array', []);
        $categoryId = isset($filter['category_id']) && '' !== $filter['category_id'] ? (int) $filter['category_id'] : null;
        $term = isset($filter['term']) ? trim((string) $filter['term']) : '';
        $types = isset($filter['types']) ? trim((string) $filter['types']) : '';
        $collection = isset($filter['collection']) ? trim((string) $filter['collection']) : '';
        $altMissing = isset($filter['alt_missing']) && '1' === (string) $filter['alt_missing'];
        $tagsRaw = isset($filter['tags']) ? trim((string) $filter['tags']) : '';

        if (null !== $categoryId && !\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($categoryId)) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $sqlWhere = [];
        $sqlParams = [];

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasFullAccess()) {
            $allowedCategoryIds = \FriendsOfRedaxo\Mediaplace\MediaPermission::getAccessibleCategoryIds();
            $sqlWhere[] = [] === $allowedCategoryIds
                ? '1 = 0'
                : 'category_id IN (' . \rex_sql::factory()->in($allowedCategoryIds) . ')';
        }

        if (null !== $categoryId) {
            $sqlWhere[':category_id'] = 'category_id = :category_id';
            $sqlParams[':category_id'] = $categoryId;
        }

        // Sammlungen (rex_mediaplace_media_tags, "collection:"-Praefix) sind ein
        // MediaPlace-eigenes Konzept, das FriendsOfRedaxo/api's /media-Route nicht
        // kennt -- der Client ruft diesen Endpunkt fuer den Sammlungs-Modus deshalb
        // IMMER direkt auf (siehe apiFetchOwnMediaList() in mediaplace-api.js),
        // unabhaengig vom data-api-media-list-secure-Schalter oben in der
        // Klassendoku (der betrifft nur die generische Kategorie-Rechtefilterung).
        // Ohne diesen Filter lud der Sammlungs-Modus schlicht Seite 1 der
        // unsortierten Gesamtliste und filterte sie clientseitig -- zeigte "0
        // Treffer", sobald die Sammlungsmitglieder nicht zufaellig auf der ersten
        // Seite lagen, obwohl die Sidebar die echte (globale) Mitgliederzahl zeigt.
        if ('' !== $collection) {
            \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureSchema();
            $sqlWhere[':collection_tag'] = 'filename IN (SELECT filename FROM ' . \rex::getTable('mediaplace_media_tags') . ' WHERE tag_name = :collection_tag)';
            $sqlParams[':collection_tag'] = \FriendsOfRedaxo\Mediaplace\SystemTagManager::COLLECTION_PREFIX . $collection;
        }

        // "Medien ohne ALT-Text"-Sidebar-Ansicht: genau wie Sammlungen ein
        // MediaPlace-eigenes Konzept (nicht ableitbar aus einer einzigen
        // SQL-WHERE-Bedingung, da die Prioritaets-Logik eigenes-vs-klassisches
        // Feld dynamische JSON-Feldkeys pro Sprache auswertet, siehe
        // AltTextStatus::isOwnValueEmpty()) -- deshalb dieselbe
        // filename-IN(...)-Strategie wie beim Sammlungs-Filter oben, nur mit
        // vorab in PHP (nicht SQL) berechneter Trefferliste.
        if ($altMissing) {
            $missingFilenames = \FriendsOfRedaxo\Mediaplace\AltTextStatus::getFilenamesMissingAlt();
            $sqlWhere[':alt_missing'] = [] === $missingFilenames
                ? '1 = 0'
                : 'filename IN (' . \rex_sql::factory()->in($missingFilenames) . ')';
        }

        // Tag-Filter (Sidebar-Auswahl, mehrere Tags = ODER-Verknuepfung --
        // spiegelt applyFilterSort()'s bisherige Client-Logik in filters.js).
        // Gleiches MediaPlace-eigenes-Konzept-Problem wie bei Sammlungen oben:
        // ohne diesen Server-Filter laed(e)t die Tag-Ansicht nur Seite 1 der
        // ungefilterten Liste und filtert clientseitig -- "0 Treffer" sobald
        // getaggte Dateien nicht zufaellig dort liegen (bei "Alle Medien" mit
        // vielen Dateien der Regelfall, nicht die Ausnahme).
        if ('' !== $tagsRaw) {
            \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureSchema();
            $tagNames = array_values(array_filter(array_map(
                static fn (string $t): string => trim($t),
                explode(',', $tagsRaw),
            ), static fn (string $t): bool => '' !== $t));
            if ([] !== $tagNames) {
                $sql = \rex_sql::factory();
                $sqlWhere[':tags'] = 'filename IN (SELECT filename FROM ' . \rex::getTable('mediaplace_media_tags') . ' WHERE tag_name IN (' . $sql->in($tagNames) . '))';
            }
        }

        // Gleiche Semantik wie api/lib/RoutePackage/Media.php::handleMediaList()
        // filter[types] (extensionExpression() IN (...)) -- genutzt von
        // mediaplace.js's Typ-Filter-Tabs/fetchTypeCounts() fuer harte
        // Endungs-Filterung statt reinem Client-Nachfiltern.
        if ('' !== $types) {
            $sql = \rex_sql::factory();
            $extensions = array_values(array_filter(array_map(
                static fn (string $t): string => strtolower(trim($t)),
                explode(',', $types),
            ), static fn (string $t): bool => '' !== $t));
            if ([] !== $extensions) {
                $sqlWhere[':types'] = 'LOWER(RIGHT(filename, LOCATE(".", REVERSE(filename))-1)) IN (' . $sql->in($extensions) . ')';
            }
        }

        // Gleiche Freitextsuche-Semantik wie api/lib/RoutePackage/Media.php::handleMediaList():
        // Anfuehrungszeichen gruppieren, "type:jpg,png" filtert die Dateiendung statt Name/Titel.
        if ('' !== $term) {
            $sql = \rex_sql::factory();
            $parts = str_getcsv($term, ' ', '"', '');
            foreach ($parts as $i => $part) {
                if (null === $part || '' === $part) {
                    continue;
                }
                if (str_starts_with($part, 'type:') && strlen($part) > 5) {
                    $extensions = explode(',', strtolower(substr($part, 5)));
                    $sqlWhere[':term_type_' . $i] = 'LOWER(RIGHT(filename, LOCATE(".", REVERSE(filename))-1)) IN (' . $sql->in($extensions) . ')';
                    continue;
                }
                $param = ':term_' . $i;
                $sqlWhere[$param] = '(filename LIKE ' . $param . ' OR title LIKE ' . $param . ')';
                $sqlParams[$param] = '%' . $sql->escapeLikeWildcards($part) . '%';
            }
        }

        $whereClause = [] !== $sqlWhere ? 'WHERE ' . implode(' AND ', $sqlWhere) : '';

        $countSql = \rex_sql::factory();
        $countResult = $countSql->getArray(
            'SELECT COUNT(*) as total FROM ' . \rex::getTable('media') . ' ' . $whereClause,
            $sqlParams,
        );
        $total = (int) $countResult[0]['total'];

        // "page" NICHT als Parametername verwenden: rex_be_controller liest
        // $_GET['page'] selbst, um die Backend-Seite zu bestimmen. Ein
        // rex-api-call-Request mit &page=1 im Query-String wird dadurch als
        // "zeig Backend-Seite '1'" fehlinterpretiert -- nicht gefunden, also
        // 302-Redirect auf die Standardseite (HTML statt JSON, "Unexpected
        // token '<'" im Client). Deshalb hier "mp_page"/"mp_per_page";
        // apiFetchMediaList() (mediaplace-api.js) benennt beim Aufbau der
        // Fallback-URL entsprechend um.
        $page = max(1, rex_request('mp_page', 'int', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, rex_request('mp_per_page', 'int', 100)));
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $mediaSql = \rex_sql::factory();
        $medias = $mediaSql->getArray(
            'SELECT ' . implode(',', self::MEDIA_FIELDS) . '
            FROM ' . \rex::getTable('media') . '
            ' . $whereClause . '
            ORDER BY ' . self::buildOrderBy((string) rex_request('sort', 'string', '')) . '
            LIMIT ' . (int) $offset . ', ' . (int) $perPage,
            $sqlParams,
        );

        \rex_response::sendJson([
            'data' => $medias,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
        exit;
    }

    /**
     * Gleiche Sort-Syntax wie api's ListHelper::parseSort(): "feld:richtung"
     * (Default "asc"), kommasepariert fuer mehrere Felder. mediaplace.js
     * schickt sie ueber SORT_API_MAP (buildMediaEndpoint()). Ohne dieses
     * Mapping bliebe es immer bei "filename ASC" (wie beim api-Addon-Default),
     * wodurch neu hochgeladene Dateien bei "Neueste zuerst" und vielen
     * vorhandenen Medien gar nicht erst auf der ersten Seite mitgeladen
     * wuerden -- die Sortierung im Client wirkt nur auf bereits geladene
     * Seiten, nicht rueckwirkend auf die Server-Pagination.
     */
    private static function buildOrderBy(string $sort): string
    {
        $allowedFields = ['filename', 'category_id', 'filetype', 'filesize', 'title', 'createdate', 'updatedate', 'width', 'height'];
        $default = 'filename ASC';

        if ('' === $sort) {
            return $default;
        }

        $orderParts = [];
        foreach (explode(',', $sort) as $part) {
            $segments = explode(':', trim($part), 2);
            $field = trim($segments[0]);
            $direction = isset($segments[1]) ? strtolower(trim($segments[1])) : 'asc';

            if (!in_array($field, $allowedFields, true) || !in_array($direction, ['asc', 'desc'], true)) {
                continue;
            }

            $orderParts[] = '`' . $field . '` ' . strtoupper($direction);
        }

        return [] !== $orderParts ? implode(', ', $orderParts) : $default;
    }
}
