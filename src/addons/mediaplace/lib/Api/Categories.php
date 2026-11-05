<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Flat category list + category add/rename/delete/move endpoint.
 *
 * GET    → returns all media categories as a flat, sorted list with full path labels
 * POST   {name, parent_id} → creates a category
 * PATCH  {id, name} → renames a category
 * PATCH  {id, parent_id} → moves a category to a new parent
 * DELETE ?id=<int> → deletes a category
 *
 * Add/Rename/Delete laufen bewusst NICHT ueber das FriendsOfRedaxo/api-Addon
 * (media/category/*), obwohl das diese drei Operationen anbietet: dessen
 * Rechtepruefung (checkMediaPerm() auf die Ziel-Kategorie selbst) erlaubt einem
 * User mit Zugriff NUR auf Kategorie X, X selbst umzubenennen/zu loeschen --
 * die ihm zugewiesene Ordnergrenze waere damit vom User selbst aufloesbar, ein
 * Integritaetsrisiko fuer die festgelegte Ordner-Hauptstruktur. Siehe
 * MediaPermission::hasParentCategoryAccess() fuer das gewollte Modell (Zugriff
 * auf die ELTERN-Kategorie noetig, nicht auf die Kategorie selbst) und den
 * Vergleich mit klassischem REDAXO (dort strenger: PERMALL-only, siehe dortiger
 * Kommentar). Die eigentliche Persistenz bleibt trotzdem identisch zu dem, was
 * das api-Addon intern aufruft (rex_media_category_service::addCategory()/
 * editCategory()/deleteCategory()) -- nur die Rechtepruefung davor ist eigen.
 *
 * Kategorie-Verschieben (handleMove()) ist zusaetzlich komplett eigenstaendig
 * implementiert (direktes SQL auf rex_media_category), weil das api-Addon dafuer
 * gar keine Route anbietet -- `media/category/update` dort aendert laut eigenem
 * Kommentar nur den Namen (mirrored "REDAXO core does not allow parent_id
 * changes via the page"). Im api-Addon-Issue dazu (Antwort von Jan, api-Addon-
 * Maintainer) bleibt das bewusst zurueckgestellt: ein Move waere die erste
 * Erweiterung der API ueber den Core hinaus (samt neuem Extension Point) und
 * das ist eine Grundsatzentscheidung, keine Move-Route also erstmal nicht zu
 * erwarten -- unsere Eigenloesung bleibt also auf absehbare Zeit der einzige
 * Weg. Der GET-Teil (flache Liste mit depth) bleibt ohnehin noetig, da
 * media/category/list nur direkte Kinder pro Kategorie liefert, kein flaches
 * Gesamtergebnis mit Tiefenangabe.
 *
 * WICHTIG (von Jan im selben Issue aufgezeigt): rex_media_category.path speichert
 * die Vorfahrenkette ("|1|4|", siehe rex_media_category_service::addCategory(),
 * mediapool/lib/service_media_category.php:20-23) und wird vom Core nie nach dem
 * Anlegen angefasst -- es gibt dort kein Move, also auch keinen Pflegecode dafuer.
 * Weil das unsere eigene Verantwortung ist (kein Core-Pendant zum Spiegeln), muss
 * handleMove() path selbst fuer die verschobene Kategorie UND ihren gesamten
 * Teilbaum neu schreiben. Ohne das bleibt api's `filter[category_id_path]`
 * (`path LIKE '%|id|%'`, siehe api/lib/RoutePackage/Media.php) auf dem alten Baum
 * stehen und liefert still falsche Ergebnisse.
 */
class Categories extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex_backend_login::hasSession()) {
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasMediaAccess()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'DELETE') {
            $this->handleDelete();
        } elseif ($method === 'POST') {
            $this->handleAdd();
        } elseif ($method === 'PATCH') {
            $body = (string) file_get_contents('php://input');
            $data = json_decode($body, true);
            if (!is_array($data)) {
                $data = $_POST;
            }
            // parent_id unterscheidet Move (id + parent_id) von Rename (id + name,
            // kein parent_id) -- apiMoveCategory()/apiRenameCategory() in
            // mediaplace-api.js senden entsprechend nur eines von beiden mit.
            if (array_key_exists('parent_id', $data)) {
                $this->handleMove($data);
            } else {
                $this->handleRename($data);
            }
        } else {
            $this->handleList();
        }

        exit;
    }

    private function handleList(): void
    {
        $currentCat = rex_request('current_cat', 'int', 0);

        \rex_response::sendJson([
            'categories' => self::getFlatCategoryList(),
            'tree_html' => $this->renderTreeHtml($currentCat),
        ]);
    }

    /**
     * Flache, tiefensortierte Kategorienliste mit eingerueckten Pfad-Labels
     * (id/name/parent_id/label/depth). Genutzt fuer Auswahllisten (Kategorie
     * verschieben, Detail-Panel-Info-Tabelle) -- Gegenstueck zu renderTreeHtml(),
     * das dieselbe Struktur als verschachteltes HTML fuer den Sidebar-Baum liefert.
     * Beide teilen sich filterVisibleCategories() fuer die Rechtepruefung.
     *
     * @return list<array{id:int,name:string,parent_id:int,label:string,depth:int}>
     */
    public static function getFlatCategoryList(): array
    {
        $result = [];
        self::collectFlatCategories(self::filterVisibleCategories(\rex_media_category::getRootCategories()), 0, $result, '', 0);

        return $result;
    }

    /**
     * @param list<\rex_media_category>   $categories
     * @param list<array<string, mixed>> $result
     */
    private static function collectFlatCategories(array $categories, int $parentId, array &$result, string $prefix, int $depth): void
    {
        foreach ($categories as $category) {
            $id = $category->getId();
            $label = $prefix . $category->getName();
            $result[] = [
                'id' => $id,
                'name' => $category->getName(),
                'parent_id' => $parentId,
                'label' => $label,
                'depth' => $depth,
            ];
            self::collectFlatCategories(self::filterVisibleCategories($category->getChildren()), $id, $result, $prefix . '  ', $depth + 1);
        }
    }

    /**
     * Filtert eine Kategorienliste auf die fuer den aktuellen User sichtbaren
     * Eintraege: nicht erlaubte Kategorien werden uebersprungen, ihre erlaubten
     * Nachfahren aber trotzdem eingesammelt (an dieser Stelle "hochgezogen"),
     * damit z.B. eine erlaubte Unterkategorie unter einer nicht erlaubten
     * Elternkategorie nicht unerreichbar wird. Exakt das Muster aus
     * mediapool/lib/media_category_select.php::addCatOption() (dort wird beim
     * Ueberspringen ebenfalls der aeussere $parentId an die Kinder weitergereicht),
     * nur hier fuer eine Liste statt fuer eine einzelne Select-Box.
     *
     * @param list<\rex_media_category> $categories
     * @return list<\rex_media_category>
     */
    public static function filterVisibleCategories(array $categories): array
    {
        if (\FriendsOfRedaxo\Mediaplace\MediaPermission::hasFullAccess()) {
            return $categories;
        }

        $result = [];
        foreach ($categories as $category) {
            if (\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($category->getId())) {
                $result[] = $category;
            } else {
                $result = array_merge($result, self::filterVisibleCategories($category->getChildren()));
            }
        }

        return $result;
    }

    /**
     * Baut den kompletten, verschachtelten Sidebar-Kategoriebaum als HTML
     * (fragments/mediaplace/category_children.php + category_node.php).
     * Ersetzt das fruehere lazy Nachladen pro Ebene (siehe frueheres
     * loadCategories()/toggleCategory() in mediaplace.js, das dafuer die
     * api-Addon-Route media/category nutzte) -- bei der ueberschaubaren
     * Kategorienzahl typischer Installationen lohnt sich ein Request fuer
     * den ganzen Baum mehr als ein Request pro Aufklappen, und das Markup
     * lebt jetzt in Templates statt als JS-String-Konkatenation.
     */
    private function renderTreeHtml(int $currentCat): string
    {
        $fragment = new \rex_fragment();
        $fragment->setVar('categories', self::filterVisibleCategories(\rex_media_category::getRootCategories()), false);
        $fragment->setVar('depth', 0, false);
        $fragment->setVar('current_cat', $currentCat, false);

        return $fragment->parse('mediaplace/category_children.php');
    }

    /**
     * @param array<string, mixed> $data Bereits geparster PATCH-Body aus execute() (id, parent_id)
     */
    private function handleMove(array $data): void
    {
        $catId = (int) ($data['id'] ?? 0);
        $newParentId = (int) ($data['parent_id'] ?? 0);

        if ($catId <= 0) {
            \rex_response::sendJson(['error' => 'Missing id']);
            exit;
        }

        $cat = \rex_media_category::get($catId);
        if (!$cat) {
            \rex_response::sendJson(['error' => 'Category not found']);
            exit;
        }

        // Rechte auf Quelle und Ziel pruefen: Quelle = Zugriff auf die AKTUELLE
        // Elternkategorie noetig (die Kategorie wird dort herausgenommen), nicht
        // auf die verschobene Kategorie selbst -- siehe
        // MediaPermission::hasParentCategoryAccess(). Verschieben nach
        // "Hauptverzeichnis" (newParentId 0) ist ebenfalls kategorieuebergreifend
        // -> volles Medienrecht noetig, identische Semantik wie beim Ziel.
        $hasSourcePerm = \FriendsOfRedaxo\Mediaplace\MediaPermission::hasParentCategoryAccess($cat->getParentId());
        $hasTargetPerm = \FriendsOfRedaxo\Mediaplace\MediaPermission::hasParentCategoryAccess($newParentId);
        if (!$hasSourcePerm || !$hasTargetPerm) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        // Prevent moving to own subtree
        if ($newParentId > 0 && $this->isDescendant($catId, $newParentId)) {
            \rex_response::sendJson(['error' => 'Cannot move a category into its own subtree']);
            exit;
        }

        $newParentCat = null;
        if ($newParentId > 0) {
            $newParentCat = \rex_media_category::get($newParentId);
            if (!$newParentCat) {
                \rex_response::sendJson(['error' => 'Target parent category not found']);
                exit;
            }
        }

        // path = Vorfahrenkette ("|1|4|"), siehe Klassenkommentar oben. parent_id
        // allein reicht nicht -- path muss fuer die Kategorie und ihren gesamten
        // Teilbaum neu geschrieben werden, sonst bleibt filter[category_id_path]
        // im api-Addon auf dem alten Baum stehen.
        $oldPath = $cat->getPath();
        $newParentPath = $newParentCat ? $newParentCat->getPath() . $newParentCat->getId() . '|' : '|';
        $oldPrefix = $oldPath . $catId . '|';
        $newPrefix = $newParentPath . $catId . '|';

        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTablePrefix() . 'media_category');
        $sql->setWhere(['id' => $catId]);
        $sql->setValue('parent_id', $newParentId);
        $sql->setValue('path', $newParentPath);
        $sql->addGlobalUpdateFields();
        $sql->update();

        $affectedIds = [$catId];
        if ($oldPrefix !== $newPrefix) {
            $descendants = \rex_sql::factory()->getArray(
                'SELECT id, path FROM ' . \rex::getTablePrefix() . 'media_category WHERE path LIKE :prefix',
                [':prefix' => $oldPrefix . '%'],
            );
            foreach ($descendants as $d) {
                $descId = (int) $d['id'];
                $newChildPath = $newPrefix . substr((string) $d['path'], strlen($oldPrefix));
                $upd = \rex_sql::factory();
                $upd->setTable(\rex::getTablePrefix() . 'media_category');
                $upd->setWhere(['id' => $descId]);
                $upd->setValue('path', $newChildPath);
                $upd->addGlobalUpdateFields();
                $upd->update();
                $affectedIds[] = $descId;
            }
        }

        foreach ($affectedIds as $affectedId) {
            \rex_media_cache::deleteCategory($affectedId);
        }
        if ($cat->getParentId() > 0) {
            \rex_media_cache::deleteCategory($cat->getParentId());
        }
        if ($newParentId > 0) {
            \rex_media_cache::deleteCategory($newParentId);
        }

        \rex_response::sendJson(['success' => true, 'id' => $catId, 'parent_id' => $newParentId]);
    }

    private function handleAdd(): void
    {
        $body = (string) file_get_contents('php://input');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $name = trim((string) ($data['name'] ?? ''));
        $parentId = (int) ($data['parent_id'] ?? 0);

        if ('' === $name) {
            \rex_response::sendJson(['error' => 'Missing name']);
            exit;
        }

        $parent = null;
        if ($parentId > 0) {
            $parent = \rex_media_category::get($parentId);
            if (!$parent) {
                \rex_response::sendJson(['error' => 'Parent category not found']);
                exit;
            }
        }

        // Neue Kategorie entsteht INNERHALB von $parentId -- Zugriff auf
        // $parentId ist deshalb die richtige Pruefung (kein hasParentCategoryAccess():
        // hier ist $parentId die Kategorie, in der gearbeitet wird, nicht die
        // Kategorie, die veraendert wird).
        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($parentId)) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        // Mirror mediapool/pages/structure.php (add_file_cat): rex_media_category_service::addCategory()
        // fires MEDIA_CATEGORY_ADDED and handles cache invalidation.
        try {
            \rex_media_category_service::addCategory($name, $parent);
        } catch (\Exception $e) {
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }

        $row = \rex_sql::factory()->getArray(
            'SELECT id FROM ' . \rex::getTable('media_category') . ' WHERE parent_id = :p AND name = :n ORDER BY id DESC LIMIT 1',
            [':p' => $parentId, ':n' => $name],
        );
        $newId = isset($row[0]['id']) ? (int) $row[0]['id'] : null;

        \rex_response::sendJson(['success' => true, 'id' => $newId]);
    }

    private function handleRename(array $data): void
    {
        $catId = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));

        if ($catId <= 0) {
            \rex_response::sendJson(['error' => 'Missing id']);
            exit;
        }
        if ('' === $name) {
            \rex_response::sendJson(['error' => 'Missing name']);
            exit;
        }

        $cat = \rex_media_category::get($catId);
        if (!$cat) {
            \rex_response::sendJson(['error' => 'Category not found']);
            exit;
        }

        // Zugriff auf die ELTERN-Kategorie noetig, nicht auf $catId selbst --
        // siehe MediaPermission::hasParentCategoryAccess() und Klassenkommentar.
        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasParentCategoryAccess($cat->getParentId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        // Mirror mediapool/pages/structure.php (edit_file_cat): rex_media_category_service::editCategory()
        // nimmt nur den Namen entgegen und feuert MEDIA_CATEGORY_UPDATED. Core
        // erlaubt keine parent_id-Aenderung ueber die Seite, daher auch hier nicht.
        try {
            \rex_media_category_service::editCategory($catId, ['name' => $name]);
        } catch (\Exception $e) {
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }

        \rex_response::sendJson(['success' => true, 'id' => $catId]);
    }

    private function handleDelete(): void
    {
        $catId = rex_request('id', 'int', 0);

        if ($catId <= 0) {
            \rex_response::sendJson(['error' => 'Missing id']);
            exit;
        }

        $cat = \rex_media_category::get($catId);
        if (!$cat) {
            \rex_response::sendJson(['error' => 'Category not found']);
            exit;
        }

        // Zugriff auf die ELTERN-Kategorie noetig, nicht auf $catId selbst --
        // siehe MediaPermission::hasParentCategoryAccess() und Klassenkommentar.
        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasParentCategoryAccess($cat->getParentId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        // Mirror mediapool/pages/structure.php (delete_file_cat): rex_media_category_service::deleteCategory()
        // prueft Unterkategorien/Dateien (inkl. MEDIA_CATEGORY_IS_IN_USE) selbst
        // und feuert MEDIA_CATEGORY_DELETED.
        try {
            \rex_media_category_service::deleteCategory($catId);
        } catch (\rex_functional_exception $e) {
            // Kategorie hat noch Unterkategorien/Dateien -- Konflikt, nicht
            // Server-Fehler, analog api-Addons handleDeleteCategory() (409).
            // rex_response definiert keine HTTP_CONFLICT-Konstante (nur bis 416/500/503).
            \rex_response::setStatus('409 Conflict');
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        } catch (\Exception $e) {
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }

        \rex_response::sendJson(['success' => true, 'id' => $catId]);
    }

    private function isDescendant(int $ancestorId, int $targetId): bool
    {
        $sql = \rex_sql::factory();
        $cats = $sql->getArray(
            'SELECT id, parent_id FROM ' . \rex::getTablePrefix() . 'media_category',
        );

        $byId = [];
        foreach ($cats as $c) {
            $byId[(int) $c['id']] = (int) $c['parent_id'];
        }

        $current = $targetId;
        $visited = [];
        while ($current > 0) {
            if ($current === $ancestorId) {
                return true;
            }
            if (isset($visited[$current])) {
                break; // Cycle protection
            }
            $visited[$current] = true;
            $current = $byId[$current] ?? 0;
        }

        return false;
    }
}
