<?php

/**
 * @var rex_yform_value_mediaplace $this
 * @psalm-scope-this rex_yform_value_mediaplace
 */

$name = $this->getFieldName();
$value = rex_escape($this->getValue());
$multiple = '1' == $this->getElement('multiple');
$upload = '1' == $this->getElement('upload');
$types = trim((string) $this->getElement('types'));
$max = trim((string) $this->getElement('max'));
$view = trim((string) $this->getElement('view'));

$class_group = trim('form-group ' . $this->getHTMLClass() . ' ' . $this->getWarningClass());

$notice = [];
if ('' != $this->getElement('notice')) {
    $notice[] = rex_i18n::translate($this->getElement('notice'), false);
}
if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
    $notice[] = '<span class="text-warning">' . rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) . '</span>';
}
$notice = count($notice) > 0 ? '<p class="help-block small">' . implode('<br />', $notice) . '</p>' : '';

?>
<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>">
    <label class="control-label" for="<?= $this->getFieldId() ?>"><?= $this->getLabel() ?></label>
    <input
        class="mp-widget form-control"
        id="<?= $this->getFieldId() ?>"
        name="<?= rex_escape($name) ?>"
        value="<?= $value ?>"
        <?= $multiple ? ' data-mp-multiple="true"' : '' ?>
        <?= $upload ? ' data-mp-upload="true"' : '' ?>
        <?= '' !== $types ? ' data-mp-types="' . rex_escape($types) . '"' : '' ?>
        <?= '' !== $max ? ' data-mp-max="' . rex_escape($max) . '"' : '' ?>
        <?= '' !== $view ? ' data-mp-view="' . rex_escape($view) . '"' : '' ?>
    >
    <?= $notice ?>
</div>
