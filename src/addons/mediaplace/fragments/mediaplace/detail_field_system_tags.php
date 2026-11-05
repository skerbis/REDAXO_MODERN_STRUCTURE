<?php

/**
 * System-Tags-Feld (global, nicht Teil der Custom-Feld-Widgets), Markup
 * identisch zu renderSystemTagsField() in mediaplace.js. Sammlungs-Tags
 * ("collection:..."-Praefix) sind hier absichtlich nicht editierbar --
 * werden ueber die Sammlungsauswahl in der Sidebar verwaltet. Der Tag-Katalog
 * fuer die Combobox-Vorschlagsliste kommt clientseitig aus
 * detailSystemTagCatalog (mediaplace.js, aus dem JSON-Metadaten-Payload
 * dieser Anfrage, siehe renderDetail()) -- kein serverseitig gerendertes
 * <datalist> mehr noetig.
 *
 * Vars:
 * - list<array{name:string,color:string}> $tags    bereits collection-gefiltert
 *
 * @var rex_fragment $this
 */

$tags = $this->getVar('tags');
?>
<div class="mp-edit-field mp-json-field" data-field-key="__system_tags">
    <label class="mp-edit-label"><?= rex_escape($this->i18n('mediaplace_system_tags')) ?> <span class="mp-edit-kind-badge">global</span></label>
    <?php $this->subfragment('mediaplace/detail_tags_widget.php', [
        'key' => '__system_tags',
        'tags' => $tags,
        'with_suggestions' => true,
    ]); ?>
    <button type="button" class="mp-field-save-btn" data-save-field="__system_tags" style="display:none"><i class="fa-solid fa-floppy-disk"></i> <?= rex_escape($this->i18n('mediaplace_save')) ?></button>
</div>
