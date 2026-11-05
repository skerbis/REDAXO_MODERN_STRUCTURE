<?php

/**
 * Feldkoerper fuer widget_type "text" (Default-Fall) -- uebersetzbar via
 * detail_lang_group.php, sonst inline Klick-zum-Bearbeiten wie beim
 * Titel-Feld. Markup identisch zum default-Zweig von renderJsonWidgetField()
 * in mediaplace.js.
 *
 * Vars: $field, $value, $clangs (siehe detail_field.php)
 *
 * @var rex_fragment $this
 */

$field = $this->getVar('field');
$value = $this->getVar('value');
$clangs = $this->getVar('clangs');
$key = (string) $field['key'];

if (!empty($field['translatable'])) {
    $this->subfragment('mediaplace/detail_lang_group.php', [
        'field_key' => $key,
        'values' => is_array($value) ? $value : [],
        'multiline' => false,
        'clangs' => $clangs,
    ]);
    return;
}

// is_scalar-Schutz: ein Feld, dessen widget_type nachtraeglich geaendert
// wurde (oder ein entfernter Erweiterungspunkt-Typ, siehe MetainfoWidget::
// getRegisteredTypes()), kann hier auf altem, nicht-skalarem JSON-Wert
// landen -- kein PHP-Warning/"Array to string conversion" produzieren.
$rawValue = is_scalar($value) ? (string) $value : '';
$text = trim($rawValue);
$placeholder = $this->i18n('mediaplace_click_to_edit');
?>
<div class="mp-edit-display" data-inline-toggle="<?= rex_escape($key) ?>"><span class="mp-edit-text<?= '' !== $text ? '' : ' mp-edit-placeholder' ?>"><?= rex_escape('' !== $text ? $text : $placeholder) ?></span><i class="fa-solid fa-pen mp-edit-pen"></i></div>
<div class="mp-inline-edit-wrap" style="display:none"><input class="mp-edit-input" type="text" data-json-field="<?= rex_escape($key) ?>" value="<?= rex_escape($rawValue) ?>"></div>
