<?php

/**
 * Eine einzelne Sprachzeile innerhalb eines uebersetzbaren Feldes. Markup
 * identisch zu renderSingleLangInput() in mediaplace.js.
 *
 * Vars:
 * - string $field_key
 * - array  $clang       {id,name,code}
 * - string $value
 * - bool   $multiline
 * - string $input_class optional, zusaetzliche CSS-Klasse
 *
 * @var rex_fragment $this
 */

$fieldKey = (string) $this->getVar('field_key');
$clang = $this->getVar('clang');
$value = (string) $this->getVar('value');
$multiline = (bool) $this->getVar('multiline');
$inputClass = (string) $this->getVar('input_class', '');

$classes = 'mp-edit-input' . ('' !== $inputClass ? ' ' . $inputClass : '');
$badge = $clang['code'] ?: ($clang['name'] ?: ('L' . $clang['id']));
?>
<div class="mp-lang-row">
    <span class="mp-lang-badge"><?= rex_escape($badge) ?></span>
    <?php if ($multiline): ?>
        <textarea class="<?= rex_escape($classes) ?>" rows="3" data-json-field="<?= rex_escape($fieldKey) ?>" data-clang="<?= rex_escape((string) $clang['id']) ?>"><?= rex_escape($value) ?></textarea>
    <?php else: ?>
        <input class="<?= rex_escape($classes) ?>" type="text" data-json-field="<?= rex_escape($fieldKey) ?>" data-clang="<?= rex_escape((string) $clang['id']) ?>" value="<?= rex_escape($value) ?>">
    <?php endif; ?>
</div>
