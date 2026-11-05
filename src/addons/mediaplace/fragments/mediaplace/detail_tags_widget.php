<?php

/**
 * Tag-Chip-Liste + Eingabefeld, Markup identisch zu renderTagsWidget() in
 * mediaplace.js. Interaktion (hinzufuegen/entfernen/Farbe aendern/Combobox-
 * Vorschlagsliste) bleibt komplett JS-seitig (repaintTagsWidget(),
 * updateTagsComboList() u.a., event delegation auf overlay) -- dieses
 * Fragment liefert nur den initialen Zustand.
 *
 * Vars:
 * - string $key             data-json-field-Key (z.B. "__system_tags")
 * - list<array{name:string,color:string}> $tags
 * - bool   $with_suggestions true = Eingabefeld bekommt die Tag-Combobox
 *   (Katalog-Vorschlaege + "Neu anlegen", siehe detailSystemTagCatalog in
 *   mediaplace.js -- kein natives <datalist> mehr, siehe CHANGELOG)
 *
 * @var rex_fragment $this
 */

$key = (string) $this->getVar('key');
$tags = $this->getVar('tags');
$withSuggestions = (bool) $this->getVar('with_suggestions', false);
?>
<div class="mp-tags-widget" data-json-field-wrap="<?= rex_escape($key) ?>">
    <input type="hidden" data-json-field="<?= rex_escape($key) ?>" data-widget="tags-value" value="<?= rex_escape(json_encode($tags)) ?>">
    <div class="mp-tags-list">
        <?php foreach ($tags as $tag): ?>
            <span class="mp-tag-item">
                <span class="mp-tag-dot" style="background:<?= rex_escape($tag['color']) ?>"></span> <?= rex_escape($tag['name']) ?>
                <button type="button" class="mp-tag-remove" data-tag="<?= rex_escape($tag['name']) ?>"><i class="fa-solid fa-xmark"></i></button>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="mp-tags-input-wrap">
        <input class="mp-edit-input mp-tags-input" type="text" placeholder="<?= rex_escape($this->i18n('mediaplace_add_tag')) ?>" autocomplete="off">
        <button type="button" class="mp-tags-add-btn"><i class="fa-solid fa-plus"></i></button>
        <?php if ($withSuggestions): ?>
            <div class="mp-tags-combo-list" style="display:none"></div>
        <?php endif; ?>
    </div>
</div>
