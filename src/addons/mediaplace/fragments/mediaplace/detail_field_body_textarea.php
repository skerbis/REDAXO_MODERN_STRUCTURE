<?php

/**
 * Feldkoerper fuer widget_type "textarea". Markup identisch zum
 * 'textarea'-Zweig von renderJsonWidgetField() in mediaplace.js.
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
        'multiline' => true,
        'clangs' => $clangs,
    ]);
    return;
}
// is_scalar-Schutz, siehe detail_field_body_text.php.
$rawValue = is_scalar($value) ? (string) $value : '';
?>
<textarea class="mp-edit-input" rows="4" data-json-field="<?= rex_escape($key) ?>"><?= rex_escape($rawValue) ?></textarea>
