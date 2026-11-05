<?php

/**
 * Metadaten-Tabelle (Dateiname/Typ/Groesse/Datum/Sammlungen/Kategorie).
 * Markup identisch zum Tabellen-Teil von renderDetail() in mediaplace.js.
 * Das Kategorie-Verschieben-Select wird jetzt direkt serverseitig befuellt
 * (vorher async per apiFetchAllCategoriesFlat() nach dem Rendern) --
 * der change-Handler dafuer bleibt unveraendert JS-seitig.
 *
 * Vars:
 * - array $info             siehe detail_panel.php
 * - list<string> $collection_names
 * - list<array{id:int,name:string,parent_id:int,label:string,depth:int}> $category_list
 * - bool  $feature_collections  Einstellungen-Toggle: Sammlungen-Zeile zeigen?
 *
 * @var rex_fragment $this
 */

use FriendsOfRedaxo\Mediaplace\DetailPanelFormatter;

$info = $this->getVar('info');
$collectionNames = $this->getVar('collection_names');
$categoryList = $this->getVar('category_list');
$featureCollections = (bool) $this->getVar('feature_collections');
?>
<table class="mp-detail-table">
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_filename')) ?></td><td><?= rex_escape($info['filename']) ?></td></tr>
    <?php if ('' !== $info['originalname'] && $info['originalname'] !== $info['filename']): ?>
        <tr><td><?= rex_escape($this->i18n('mediaplace_field_original')) ?></td><td><?= rex_escape($info['originalname']) ?></td></tr>
    <?php endif; ?>
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_type')) ?></td><td><?= rex_escape($info['filetype']) ?></td></tr>
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_size')) ?></td><td><?= DetailPanelFormatter::formatBytes($info['filesize']) ?></td></tr>
    <?php if ($info['width'] && $info['height']): ?>
        <tr><td><?= rex_escape($this->i18n('mediaplace_field_dimensions')) ?></td><td><?= (int) $info['width'] ?> × <?= (int) $info['height'] ?> px</td></tr>
    <?php endif; ?>
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_created')) ?></td><td><?= rex_escape($info['created_formatted']) ?><br><small><?= rex_escape($info['createuser']) ?></small></td></tr>
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_updated')) ?></td><td><?= rex_escape($info['updated_formatted']) ?><br><small><?= rex_escape($info['updateuser']) ?></small></td></tr>
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_file_exists')) ?></td><td><?= $info['file_exists'] ? '<span class="mp-badge-yes">✓ ' . rex_escape($this->i18n('mediaplace_yes')) . '</span>' : '<span class="mp-badge-no">✗ ' . rex_escape($this->i18n('mediaplace_no')) . '</span>' ?></td></tr>
    <tr><td><?= rex_escape($this->i18n('mediaplace_field_in_use')) ?></td><td><?= $info['is_in_use'] ? '<span class="mp-badge-yes">✓ ' . rex_escape($this->i18n('mediaplace_yes')) . '</span>' : '<span class="mp-badge-no">✗ ' . rex_escape($this->i18n('mediaplace_no')) . '</span>' ?></td></tr>
    <?php if ($featureCollections): ?>
        <tr><td><?= rex_escape($this->i18n('mediaplace_field_collections')) ?></td><td><?= !empty($collectionNames) ? rex_escape(implode(', ', $collectionNames)) : '–' ?></td></tr>
    <?php endif; ?>
    <tr class="mp-move-file-row"><td><?= rex_escape($this->i18n('mediaplace_field_category')) ?></td><td>
        <div class="mp-move-file-wrap">
            <select class="mp-move-file-select" data-current-cat="<?= rex_escape((string) $info['category_id']) ?>" title="<?= rex_escape($this->i18n('mediaplace_move_to_category')) ?>">
                <option value="0"><?= rex_escape($this->i18n('mediaplace_root_category')) ?></option>
                <?php foreach ($categoryList as $cat): ?>
                    <option value="<?= rex_escape((string) $cat['id']) ?>"<?= $cat['id'] === $info['category_id'] ? ' selected' : '' ?>><?= str_repeat("\u{00A0}\u{00A0}", $cat['depth']) . rex_escape($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </td></tr>
</table>
