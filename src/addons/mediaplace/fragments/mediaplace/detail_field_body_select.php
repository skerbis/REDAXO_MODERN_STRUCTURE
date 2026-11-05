<?php

/**
 * Feldkoerper fuer widget_type "select" -- Dropdown oder Mehrfachauswahl,
 * nicht mehrsprachig (wird in pages/metainfo_fields.php erzwungen). Choices
 * kommen aus $field['options']['choices_source'] (Zeilenliste oder SQL-
 * SELECT), siehe SelectWidget::resolveChoices().
 *
 * Vars: $field, $value (siehe detail_field.php)
 *
 * @var rex_fragment $this
 */

use FriendsOfRedaxo\Mediaplace\Widgets\SelectWidget;

$field = $this->getVar('field');
$value = $this->getVar('value');
$key = (string) $field['key'];
$options = is_array($field['options'] ?? null) ? $field['options'] : [];
$multiple = !empty($options['multiple']);
$choices = SelectWidget::resolveChoices((string) ($options['choices_source'] ?? ''));

$selectedValues = $multiple
    ? array_map('strval', is_array($value) ? $value : [])
    : [is_scalar($value) ? (string) $value : ''];
?>
<select class="mp-edit-input" data-json-field="<?= rex_escape($key) ?>"<?= $multiple ? ' multiple' : '' ?>>
    <?php if (!$multiple): ?>
        <option value=""><?= rex_escape($this->i18n('mediaplace_choose_ellipsis')) ?></option>
    <?php endif; ?>
    <?php foreach ($choices as $choice): ?>
        <option value="<?= rex_escape($choice['value']) ?>"<?= in_array($choice['value'], $selectedValues, true) ? ' selected' : '' ?>><?= rex_escape($choice['label']) ?></option>
    <?php endforeach; ?>
</select>
