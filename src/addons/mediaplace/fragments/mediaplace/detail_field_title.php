<?php

/**
 * Titel-Feld (inline Klick-zum-Bearbeiten), Sonderfall ausserhalb der
 * Custom-Feld-Widgets -- Markup identisch zu renderInlineTextField()
 * in mediaplace.js. Lesen/Speichern laeuft weiterhin ueber die feste ID
 * #mp-detail-title-input (saveDetail() in mediaplace.js), nicht ueber
 * data-json-field.
 *
 * Vars:
 * - string $title aktueller Titel
 *
 * @var rex_fragment $this
 */

$title = trim((string) $this->getVar('title'));
$placeholder = $this->i18n('mediaplace_click_to_edit');
$displayText = '' !== $title ? $title : $placeholder;
?>
<div class="mp-edit-field mp-edit-field-inline" data-field-key="__title">
    <label class="mp-edit-label"><?= rex_escape($this->i18n('mediaplace_title')) ?></label>
    <div class="mp-edit-display" data-inline-toggle="__title">
        <span class="mp-edit-text<?= '' !== $title ? '' : ' mp-edit-placeholder' ?>"><?= rex_escape($displayText) ?></span>
        <i class="fa-solid fa-pen mp-edit-pen"></i>
    </div>
    <div class="mp-inline-edit-wrap" style="display:none">
        <input id="mp-detail-title-input" class="mp-edit-input" type="text" data-json-field="__title" value="<?= rex_escape($title) ?>">
    </div>
    <button type="button" class="mp-field-save-btn" data-save-field="__title" style="display:none"><i class="fa-solid fa-floppy-disk"></i> <?= rex_escape($this->i18n('mediaplace_save')) ?></button>
</div>
