<?php

/**
 * Feldkoerper fuer widget_type "media_link" (Verlinkung auf ein anderes
 * Medium, z.B. Poster-Bild). Auswahl per Klick im Raster / Zuruecksetzen
 * bleibt JS-seitig (setMediaLinkPickMode()/repaintMediaLinkWidget() in
 * mediaplace.js). Markup identisch zum 'media_link'-Zweig von
 * renderJsonWidgetField() / renderMediaLinkWidget().
 *
 * Vars: $field, $value (siehe detail_field.php)
 *
 * @var rex_fragment $this
 */

use FriendsOfRedaxo\Mediaplace\DetailPanelFormatter;

$field = $this->getVar('field');
$value = $this->getVar('value');
$key = (string) $field['key'];

$filename = is_string($value) ? $value : '';
?>
<div class="mp-media-link-widget" data-json-field-wrap="<?= rex_escape($key) ?>">
    <div class="mp-media-link-row">
        <input class="mp-edit-input" type="text" readonly data-json-field="<?= rex_escape($key) ?>" value="<?= rex_escape($filename) ?>" placeholder="<?= rex_escape($this->i18n('mediaplace_media_link_none')) ?>">
        <button type="button" class="mp-media-link-picker" data-field="<?= rex_escape($key) ?>"><i class="fa-solid fa-photo-film"></i></button>
        <button type="button" class="mp-media-link-clear" data-field="<?= rex_escape($key) ?>"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mp-media-link-pick-hint" style="display:none"><i class="fa-solid fa-circle-info"></i> <?= rex_escape($this->i18n('mediaplace_media_link_pick_hint')) ?></div>
    <?php if ('' !== $filename && DetailPanelFormatter::isImageFilename($filename)): ?>
        <?php
        $linkedMedia = rex_media::get($filename);
        $updatedate = $linkedMedia ? (string) $linkedMedia->getValue('updatedate') : '';
        $filesize = $linkedMedia ? (int) $linkedMedia->getValue('filesize') : 0;
        $previewSrc = DetailPanelFormatter::mediaThumbUrl($filename, 'rex_media_small', $updatedate, $filesize);
        ?>
        <div class="mp-media-link-preview"><img src="<?= rex_escape($previewSrc) ?>" alt=""></div>
    <?php endif; ?>
</div>
