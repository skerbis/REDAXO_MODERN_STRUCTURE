<?php

$addon = rex_addon::get('mediaplace');
$addon->setConfig('version', $addon->getVersion());

// Default-Config-Werte kommen aus package.yml (default_config:), nicht von hier.

// Register med_json_data field for storing structured metadata (replaces the old schema-based approach)
require_once __DIR__ . '/lib/MetainfoFieldGroup.php';
\FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::ensureJsonField();
require_once __DIR__ . '/lib/SystemTagManager.php';
\FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureSchema();
\FriendsOfRedaxo\Mediaplace\MediaRename::ensureSchema();

// Standard-Feld "Beschreibung" anlegen, damit das Detail-Panel nach der
// Installation nicht komplett leer ist. install.php laeuft sowohl beim
// Erst-Install als auch bei jedem Update -- die Existenzpruefung verhindert
// nur ein Duplikat, falls das Feld schon da ist; loescht ein Admin es bewusst
// wieder, taucht es beim naechsten Addon-Update erneut auf (kein Tracking
// von "war schon mal da" vorgesehen, das waere fuer diesen Fall unnoetig).
if (!\FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFieldByKey('description')) {
    \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::saveField(
        'description',
        'Beschreibung',
        'textarea',
        [],
        true,
        false,
        10,
    );
}

// Eigener Media-Manager-Typ fuer Grid-/Media-Wall-Thumbnails (previewHtml() in
// mediaplace.js): rex_media_small (200x200) reicht fuer die per Slider auf bis
// zu 360px CSS-Breite skalierbaren Kacheln nicht mehr aus -- Browser skaliert
// dann sichtbar unscharf hoch. Bewusst ein EIGENER Typ statt rex_media_medium/
// _large (Core-Defaults) zu nutzen: die koennten site-spezifisch fuer andere
// Zwecke umkonfiguriert sein, ohne dass dieses Addon davon erfaehrt. Bewusst
// klein gehalten (300x300, vormals 500x500) -- es sind reine Vorschaubilder
// fuer die Grid-/Media-Wall-Ansicht, keine Arbeitskopien, kleiner spart
// spuerbar Speicher/CPU bei der Erzeugung (siehe Bugfix zu grossen animierten
// GIFs) und Bandbreite; etwas Unschaerfe am oberen Ende des Sliders wird dafuer
// in Kauf genommen. Liste/Media-Link-Vorschau bleiben bewusst auf
// rex_media_small (feste, kleine Groesse, kein Slider).
$thumbTypeDescription = 'Medienpool via API – Grid-/Media-Wall-Thumbnail (300 × 300 px)';
$typeSql = rex_sql::factory();
$existingType = $typeSql->getArray(
    'SELECT id, description FROM ' . rex::getTable('media_manager_type') . ' WHERE name = :name',
    [':name' => 'mediaplace_thumb'],
);
$now = date('Y-m-d H:i:s');
if (empty($existingType)) {
    $typeSql->setTable(rex::getTable('media_manager_type'));
    $typeSql->setValue('status', 1);
    $typeSql->setValue('name', 'mediaplace_thumb');
    $typeSql->setValue('description', $thumbTypeDescription);
    $typeSql->setValue('createdate', $now);
    $typeSql->setValue('createuser', 'mediaplace');
    $typeSql->setValue('updatedate', $now);
    $typeSql->setValue('updateuser', 'mediaplace');
    $typeSql->insert();
    $typeId = $typeSql->getLastId();
} else {
    $typeId = (int) $existingType[0]['id'];
    if ($existingType[0]['description'] !== $thumbTypeDescription) {
        // Beschreibung nachziehen (z.B. "500 x 500 px" -> "300 x 300 px"),
        // damit sie in der Media-Manager-Typenliste nicht die tatsaechliche
        // Groesse (siehe $thumbResizeParams unten) irrefuehrend falsch anzeigt.
        $descSql = rex_sql::factory();
        $descSql->setTable(rex::getTable('media_manager_type'));
        $descSql->setWhere(['id' => $typeId]);
        $descSql->setValue('description', $thumbTypeDescription);
        $descSql->setValue('updatedate', $now);
        $descSql->setValue('updateuser', 'mediaplace');
        $descSql->update();
    }
}

// Eigene Existenzpruefung, unabhaengig vom Typ-Check oben: install.php lief
// bisher so, dass ein bereits vorhandener Typ (z.B. nach manuellem Entfernen
// des Effekts ueber die Medienmanager-UI, oder einem fruehen Abbruch nach dem
// Typ-Insert) den Effekt-Block nie wieder erreichte, weil er komplett im
// "Typ existiert nicht"-Zweig haengte. Dadurch konnte ein Update den fehlenden
// Effekt nicht mehr nachtragen -- Type und Effekt werden deshalb jetzt
// unabhaengig voneinander sichergestellt.
// 300x300 statt vormals 500x500 (siehe CHANGELOG): Grid-Kacheln sind reine
// Vorschaubilder, keine Arbeitskopien -- kleiner spart spuerbar Speicher/CPU
// bei der Erzeugung (insbesondere bei grossen animierten GIFs, siehe dortiger
// Bugfix) und Bandbreite, auf Kosten von etwas Unschaerfe am oberen Ende des
// Kachelgroessen-Sliders (bis 360px CSS-Breite) auf sehr hochaufloesenden
// Displays -- bewusst in Kauf genommen.
$thumbResizeParams = [
    'rex_effect_resize' => [
        'rex_effect_resize_width' => '300',
        'rex_effect_resize_height' => '300',
        'rex_effect_resize_style' => 'maximum',
        'rex_effect_resize_allow_enlarge' => 'not_enlarge',
    ],
];
$existingEffect = $typeSql->getArray(
    'SELECT id, parameters FROM ' . rex::getTable('media_manager_type_effect') . ' WHERE type_id = :type_id AND effect = :effect',
    [':type_id' => $typeId, ':effect' => 'resize'],
);
if (empty($existingEffect)) {
    $effectSql = rex_sql::factory();
    $effectSql->setTable(rex::getTable('media_manager_type_effect'));
    $effectSql->setValue('type_id', $typeId);
    $effectSql->setValue('effect', 'resize');
    $effectSql->setValue('parameters', json_encode($thumbResizeParams));
    $effectSql->setValue('priority', 1);
    $effectSql->setValue('createdate', $now);
    $effectSql->setValue('createuser', 'mediaplace');
    $effectSql->setValue('updatedate', $now);
    $effectSql->setValue('updateuser', 'mediaplace');
    $effectSql->insert();
} elseif ($existingEffect[0]['parameters'] !== json_encode($thumbResizeParams)) {
    // Bestehende Installation, Groesse hat sich seitdem geaendert (500 -> 300)
    // -- Parameter nachziehen, damit auch aeltere Installationen beim naechsten
    // Addon-Update die kleinere Zielgroesse bekommen, ohne den Typ manuell neu
    // anlegen zu muessen. Bereits gecachte 500x500-Thumbnails bleiben liegen,
    // bis sie durch eine neue Anfrage (aktualisierte Datei o.ae.) ersetzt
    // werden -- kein aktives Cache-Purge hier, um nicht bei jedem Update alle
    // Vorschaubilder auf einen Schlag neu erzeugen zu muessen.
    $effectSql = rex_sql::factory();
    $effectSql->setTable(rex::getTable('media_manager_type_effect'));
    $effectSql->setWhere(['id' => $existingEffect[0]['id']]);
    $effectSql->setValue('parameters', json_encode($thumbResizeParams));
    $effectSql->setValue('updatedate', $now);
    $effectSql->setValue('updateuser', 'mediaplace');
    $effectSql->update();
}

// Register med_json_data in REDAXO core metainfo addon so it appears in field list
// (visible in the classic Medienpool edit form, e.g. for AddOns that don't support
// MediaPlace yet). Eigener Metainfo-Typ statt "textarea" (type_id=2, fruehere
// Version dieses Blocks): rex_metainfo_handler::renderMetaFields() rendert nur
// bekannte Typ-Labels (text/textarea/select/...) als Eingabefeld -- ein Label,
// das keinem davon entspricht, faellt in den default-Zweig und feuert den
// METAINFO_CUSTOM_FIELD-Erweiterungspunkt, den boot.php fuer eine formatierte,
// nicht editierbare Anzeige nutzt (rohes JSON als Textarea war editierbar genug,
// um das gespeicherte JSON versehentlich zu zerstoeren).
if (rex_addon::get('metainfo')->isInstalled()) {
    $typeSql2 = rex_sql::factory();
    $existingJsonType = $typeSql2->getArray(
        'SELECT id FROM ' . rex::getTable('metainfo_type') . ' WHERE label = :label',
        [':label' => 'mediaplace_json'],
    );
    if (empty($existingJsonType)) {
        $typeSql2->setTable(rex::getTable('metainfo_type'));
        $typeSql2->setValue('label', 'mediaplace_json');
        $typeSql2->setValue('dbtype', 'text');
        $typeSql2->setValue('dblength', 0);
        $typeSql2->insert();
        $jsonTypeId = (int) $typeSql2->getLastId();
    } else {
        $jsonTypeId = (int) $existingJsonType[0]['id'];
    }

    $metainfoSql = rex_sql::factory();
    $existing = $metainfoSql->getArray(
        'SELECT id, type_id FROM ' . rex::getTable('metainfo_field') . ' WHERE name = :name',
        [':name' => 'med_json_data'],
    );
    if (empty($existing)) {
        $metainfoSql->setTable(rex::getTable('metainfo_field'));
        $metainfoSql->setValue('name', 'med_json_data');
        $metainfoSql->setValue('title', 'MediaPlace Metadaten');
        $metainfoSql->setValue('type_id', $jsonTypeId);
        $metainfoSql->setValue('priority', 100);
        $metainfoSql->setValue('attributes', '');
        $metainfoSql->setValue('default', '');
        $metainfoSql->setValue('createdate', date('Y-m-d H:i:s'));
        $metainfoSql->setValue('createuser', 'mediaplace');
        $metainfoSql->setValue('updatedate', date('Y-m-d H:i:s'));
        $metainfoSql->setValue('updateuser', 'mediaplace');
        $metainfoSql->insert();
    } elseif ($jsonTypeId !== (int) $existing[0]['type_id']) {
        // Bestehende Installation mit der alten type_id=2 (Textarea) --
        // auf den eigenen Custom-Typ migrieren.
        $metainfoSql->setTable(rex::getTable('metainfo_field'));
        $metainfoSql->setWhere(['id' => (int) $existing[0]['id']]);
        $metainfoSql->setValue('type_id', $jsonTypeId);
        $metainfoSql->setValue('attributes', '');
        $metainfoSql->setValue('title', 'MediaPlace Metadaten');
        $metainfoSql->update();
    }
}
