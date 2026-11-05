<?php

/**
 * Detail-Panel (rechtes Metadaten-Panel) fuer eine einzelne Mediendatei.
 * Ersetzt renderDetail() + zugehoerige Render-Helfer in mediaplace.js.
 *
 * Markup ist bewusst 1:1 identisch zu dem, was renderDetail() vorher per
 * JS-String-Konkatenation gebaut hat -- gleiche Klassen/data-Attribute,
 * damit die bestehende Event-Delegation (overlay.addEventListener(...) in
 * mediaplace.js) unveraendert weiterfunktioniert. Interaktion/Speichern
 * bleibt komplett JS-seitig (collectJsonValuesFromDetail()/saveDetail()),
 * dieses Fragment liefert nur den initialen Rendering-Zustand.
 *
 * Vars:
 * - array $info               filename/title/filetype/filesize/width/height/
 *                              created_formatted/createuser/updated_formatted/
 *                              updateuser/is_image/is_in_use/file_exists/category_id
 * - array $data                aktuelle Feldwerte (med_json_data)
 * - array $fields               Felddefinitionen (id/key/label/widget_type/options/translatable/image_only)
 * - array $clangs                Sprachen (id/name/code)
 * - array $system_tags_normal    System-Tags ohne Sammlungs-Tags
 * - array $system_tag_catalog    globaler Tag-Katalog (Autocomplete)
 * - array $collection_names      Namen der Sammlungen, denen die Datei angehoert
 * - array $category_list         flache Kategorienliste (Verschieben-Select)
 * - bool  $feature_own_metadata  Einstellungen-Toggle: eigene Metadaten-Felder zeigen?
 * - bool  $feature_tagging       Einstellungen-Toggle: System-Tags-Feld zeigen?
 * - bool  $feature_collections   Einstellungen-Toggle: Sammlungen-Zeile zeigen?
 * - bool  $feature_metainfo_editing  Einstellungen-Toggle: "Metadaten bearbeiten"-Button zeigen?
 * - bool  $alt_text_missing      ALT-Text fehlt (eigenes "alt"-Feld falls aktiv, sonst med_alt)?
 *
 * @var rex_fragment $this
 */

/** @var array<string, mixed> $info */
$info = $this->getVar('info');
$data = $this->getVar('data');
$fields = $this->getVar('fields');
$clangs = $this->getVar('clangs');
$systemTagsNormal = $this->getVar('system_tags_normal');
$collectionNames = $this->getVar('collection_names');
$categoryList = $this->getVar('category_list');
$featureOwnMetadata = (bool) $this->getVar('feature_own_metadata');
$featureTagging = (bool) $this->getVar('feature_tagging');
$featureCollections = (bool) $this->getVar('feature_collections');
$featureMetainfoEditing = (bool) $this->getVar('feature_metainfo_editing');
$altTextMissing = (bool) $this->getVar('alt_text_missing');

?>
<div class="mp-detail-inner">
    <div class="mp-detail-header">
        <span class="mp-detail-header-name"><?= rex_escape($this->i18n('mediaplace_details')) ?></span>
        <button class="mp-detail-close" title="<?= rex_escape($this->i18n('mediaplace_close')) ?>"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <?php $this->subfragment('mediaplace/detail_preview.php', [
        'info' => $info,
        'fields' => $fields,
        'data' => $data,
    ]); ?>

    <div class="mp-edit-section">
        <?php $this->subfragment('mediaplace/detail_field_title.php', [
            'title' => $info['title'],
        ]); ?>

        <?php if ($featureMetainfoEditing): ?>
            <button type="button" class="mp-metainfo-native-edit-btn mp-metainfo-canvas-open"
                    data-canvas-file="<?= rex_escape($info['filename']) ?>"
                    data-canvas-label="<?= rex_escape($info['filename']) ?>">
                <i class="fa-solid fa-lemon"></i> <?= rex_escape($this->i18n('mediaplace_metainfo_edit_native')) ?>
            </button>
            <?php if ($altTextMissing): ?>
                <p class="mp-alt-missing-hint"><i class="fa-solid fa-triangle-exclamation"></i> <?= rex_escape($this->i18n('mediaplace_alt_text_missing')) ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($fields)): ?>
            <?php /* Bewusst per CSS statt if/endif ausgeblendet (nicht $featureOwnMetadata
                     in die Bedingung oben): die data-json-field-Elemente muessen im DOM
                     bleiben, sonst liest collectJsonValuesFromDetail() beim naechsten
                     Speichern (z.B. nur Titel geaendert) fuer diese Felder nichts mehr aus
                     und wuerde bereits gespeicherte Werte mit null ueberschreiben. */ ?>
            <div class="mp-own-metadata-fields"<?= $featureOwnMetadata ? '' : ' style="display:none"' ?>>
                <?php foreach ($fields as $field): ?>
                    <?php
                    $fieldKey = (string) $field['key'];
                    $fieldValue = array_key_exists($fieldKey, $data) ? $data[$fieldKey] : null;
                    $this->subfragment('mediaplace/detail_field.php', [
                        'field' => $field,
                        'value' => $fieldValue,
                        'info' => $info,
                        'clangs' => $clangs,
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php /* Zuletzt, direkt vor den technischen Details (detail_info_table.php
                 unterhalb dieses .mp-edit-section-Blocks) -- Nutzer-Feedback:
                 Tags sollen immer nach den eigenen Metadaten-Feldern stehen, nicht
                 davor. */ ?>
        <?php if ($featureTagging): ?>
            <?php $this->subfragment('mediaplace/detail_field_system_tags.php', [
                'tags' => $systemTagsNormal,
            ]); ?>
        <?php endif; ?>
    </div>

    <?php $this->subfragment('mediaplace/detail_info_table.php', [
        'info' => $info,
        'collection_names' => $collectionNames,
        'category_list' => $categoryList,
        'feature_collections' => $featureCollections,
    ]); ?>

    <?php $this->subfragment('mediaplace/detail_actions.php', [
        'info' => $info,
        'rename_available' => (bool) $this->getVar('rename_available'),
        'feature_collections' => $featureCollections,
        'has_collections' => !empty($collectionNames),
    ]); ?>
</div>
