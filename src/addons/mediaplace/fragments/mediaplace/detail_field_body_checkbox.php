<?php

/**
 * Feldkoerper fuer widget_type "checkbox" -- einfacher Ja/Nein-Schalter,
 * nicht mehrsprachig (wird in pages/metainfo_fields.php erzwungen). Der
 * Feld-Name steht bereits ueber der .mp-edit-label-Huelle
 * (detail_field.php), daher hier nur ein knapper "Ja"-Beschriftungstext statt
 * einer Wiederholung des Labels.
 *
 * Vars: $field, $value (siehe detail_field.php)
 *
 * @var rex_fragment $this
 */

$field = $this->getVar('field');
$value = $this->getVar('value');
$key = (string) $field['key'];
$checked = (bool) $value;
?>
<label class="mp-edit-checkbox-label">
    <input type="checkbox" data-json-field="<?= rex_escape($key) ?>"<?= $checked ? ' checked' : '' ?>>
    <?= rex_escape($this->i18n('mediaplace_yes')) ?>
</label>
