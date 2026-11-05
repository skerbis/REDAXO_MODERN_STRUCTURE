<?php

/**
 * Dispatcher fuer ein einzelnes Custom-Feld (Widget-Klassen-System) --
 * rendert die gemeinsame .mp-edit-field.mp-json-field-Huelle (Label +
 * Speichern-Button) und delegiert den eigentlichen Feldkoerper an das
 * fuer den widget_type registrierte Subfragment, siehe
 * MetainfoWidget::getRegisteredTypes() -- eingebaute Typen wie externe
 * (per rex_extension registrierte) Widgets laufen ueber denselben Weg.
 *
 * Vars:
 * - array $field  Felddefinition (key/label/widget_type/translatable/...)
 * - mixed $value  aktueller Wert aus med_json_data
 * - array $info   siehe detail_panel.php
 * - array $clangs Sprachen (id/name/code)
 *
 * @var rex_fragment $this
 */

use FriendsOfRedaxo\Mediaplace\MetainfoWidget;

$field = $this->getVar('field');
$value = $this->getVar('value');
$info = $this->getVar('info');
$clangs = $this->getVar('clangs');

$key = (string) $field['key'];
$widget = (string) ($field['widget_type'] ?: 'text');
$label = '' !== (string) $field['label'] ? $field['label'] : $key;

$registeredTypes = MetainfoWidget::getRegisteredTypes();
$bodyFragment = $registeredTypes[$widget]['fragment'] ?? 'mediaplace/detail_field_body_text.php';
?>
<div class="mp-edit-field mp-json-field" data-field-key="<?= rex_escape($key) ?>">
    <label class="mp-edit-label"><?= rex_escape($label) ?></label>
    <?php $this->subfragment($bodyFragment, [
        'field' => $field,
        'value' => $value,
        'info' => $info,
        'clangs' => $clangs,
    ]); ?>
    <button type="button" class="mp-field-save-btn" data-save-field="<?= rex_escape($key) ?>" style="display:none"><i class="fa-solid fa-floppy-disk"></i> <?= rex_escape($this->i18n('mediaplace_save')) ?></button>
</div>
