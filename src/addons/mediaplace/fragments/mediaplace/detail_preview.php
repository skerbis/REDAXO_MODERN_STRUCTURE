<?php

/**
 * Vorschau-Bereich des Detail-Panels: Bild/Video/Audio/Icon je nach Dateityp.
 *
 * Vars:
 * - array $info   siehe detail_panel.php
 * - array $fields Felddefinitionen (aktuell ungenutzt, fuer kuenftige
 *                  Erweiterungspunkt-Widgets mit eigener Vorschau-Overlay-
 *                  Schicht mituebergeben)
 * - array $data   aktuelle Feldwerte
 *
 * @var rex_fragment $this
 */

use FriendsOfRedaxo\Mediaplace\DetailPanelFormatter;

/** @var array<string, mixed> $info */
$info = $this->getVar('info');
$filename = $info['filename'];
?>
<?php if ($info['is_image']): ?>
    <?php
    $src = DetailPanelFormatter::mediaThumbUrl($filename, 'rex_media_medium', $info['updatedate'], $info['filesize']);
    // Lightbox zeigt die Original-Datei in Upload-Qualitaet, nicht die kleine
    // rex_media_medium-Vorschau -- dafuer will man vergroessern.
    $lightboxSrc = DetailPanelFormatter::mediaFileUrl($filename, $info['updatedate'], $info['filesize']);
    ?>
    <div class="mp-detail-preview">
        <button type="button" class="mp-lightbox-open-btn" data-lightbox-src="<?= rex_escape($lightboxSrc) ?>" data-lightbox-caption="<?= rex_escape($info['title'] !== '' ? $info['title'] : $filename) ?>" title="<?= rex_escape($this->i18n('mediaplace_open_lightbox')) ?>"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
        <?php if (!empty($info['focuspoint_available'])): ?>
            <button type="button" class="mp-focuspoint-edit-btn" data-focuspoint-file="<?= rex_escape($filename) ?>" title="<?= rex_escape($this->i18n('mediaplace_edit_focuspoint')) ?>"><i class="fa-solid fa-crosshairs"></i></button>
        <?php endif; ?>
        <?php if (!empty($info['image_edit_available'])): ?>
            <button type="button" class="mp-image-edit-btn" data-image-edit-file="<?= rex_escape($filename) ?>" title="<?= rex_escape($this->i18n('mediaplace_image_edit')) ?>"><i class="fa-solid fa-sliders"></i></button>
        <?php endif; ?>
        <img src="<?= rex_escape($src) ?>" alt="<?= rex_escape($info['title'] !== '' ? $info['title'] : $filename) ?>">
    </div>
    <?php if (!empty($info['optimize_image_available'])): ?>
        <?php
        $target = $info['optimize_image_target'] ?? null;
        $optimizeImageLabel = $target
            ? $this->i18n('mediaplace_optimize_image_target', $info['width'], $info['height'], $target['width'], $target['height'])
            : $this->i18n('mediaplace_optimize_image');
        ?>
        <button type="button" class="mp-image-optimize-btn" data-optimize-image-file="<?= rex_escape($filename) ?>" title="<?= rex_escape($optimizeImageLabel) ?>">
            <i class="fa-solid fa-compress"></i> <span class="mp-image-optimize-btn-label"><?= rex_escape($optimizeImageLabel) ?></span>
        </button>
        <div class="mp-image-optimize-status" style="display:none"></div>
    <?php endif; ?>
<?php elseif (DetailPanelFormatter::isVideoFilename($filename)): ?>
    <?php $vidSrc = DetailPanelFormatter::mediaFileUrl($filename, $info['updatedate'], $info['filesize']); ?>
    <div class="mp-detail-preview mp-detail-preview-video">
        <video controls preload="metadata" playsinline>
            <source src="<?= rex_escape($vidSrc) ?>" type="<?= rex_escape($info['filetype'] ?: 'video/mp4') ?>">
        </video>
    </div>
    <?php if (!empty($info['optimize_video_available'])): ?>
        <?php $optimizedStatus = $info['optimize_video_status'] ?? null; ?>
        <button type="button" class="mp-video-optimize-btn<?= $optimizedStatus ? ' mp-video-optimize-btn-secondary' : '' ?>" data-optimize-video-file="<?= rex_escape($filename) ?>"
                <?php if (!empty($info['optimize_video_job'])): ?>data-optimize-video-job="<?= rex_escape((string) json_encode($info['optimize_video_job'])) ?>"<?php endif; ?>
        ><i class="fa-solid fa-arrows-rotate"></i> <?= $optimizedStatus ? $this->i18n('mediaplace_reoptimize_video') : $this->i18n('mediaplace_optimize_video') ?></button>
        <?php if ($optimizedStatus): ?>
            <p class="mp-video-optimize-badge"><i class="fa-solid fa-check"></i> <?= $optimizedStatus['compressionRate'] > 0
                ? $this->i18n('mediaplace_video_already_optimized_rate', $optimizedStatus['compressionRate'])
                : $this->i18n('mediaplace_video_already_optimized') ?></p>
        <?php endif; ?>
        <div class="mp-video-optimize-status" style="display:none"></div>
    <?php endif; ?>
    <?php if (!empty($info['video_details_available'])): ?>
        <button type="button" class="mp-video-details-toggle" data-video-details-file="<?= rex_escape($filename) ?>" aria-expanded="false">
            <i class="fa-solid fa-chevron-right"></i> <?= rex_escape($this->i18n('mediaplace_video_details_toggle')) ?>
        </button>
        <div class="mp-video-details-body" style="display:none"></div>
    <?php endif; ?>
<?php elseif (DetailPanelFormatter::isAudioFilename($filename)): ?>
    <?php $audSrc = DetailPanelFormatter::mediaFileUrl($filename, $info['updatedate'], $info['filesize']); ?>
    <div class="mp-detail-preview mp-detail-preview-audio">
        <i class="<?= rex_escape(DetailPanelFormatter::fileIconClass($filename)) ?>"></i>
        <audio controls preload="metadata">
            <source src="<?= rex_escape($audSrc) ?>" type="<?= rex_escape($info['filetype'] ?: 'audio/mpeg') ?>">
        </audio>
    </div>
<?php elseif (DetailPanelFormatter::isPdfFilename($filename)): ?>
    <?php
    $pdfSrc = DetailPanelFormatter::mediaFileUrl($filename, $info['updatedate'], $info['filesize']);
    $pdfViewerUrl = (string) ($info['pdf_viewer_url'] ?? '');
    $pdfThumb = !empty($info['pdf_thumb_available'])
        ? DetailPanelFormatter::mediaThumbUrl($filename, \FriendsOfRedaxo\Mediaplace\PdfoutIntegration::PDF_THUMB_TYPE, $info['updatedate'], $info['filesize'])
        : '';
    ?>
    <div class="mp-detail-preview<?= '' === $pdfThumb ? ' mp-detail-preview-icon' : ' mp-detail-preview-pdf' ?>">
        <a class="mp-pdf-open-btn" href="<?= rex_escape($pdfSrc) ?>" target="_blank" rel="noopener" title="<?= rex_escape($this->i18n('mediaplace_open_pdf_new_tab')) ?>"><i class="fa-solid fa-up-right-from-square"></i></a>
        <?php if ('' !== $pdfViewerUrl): ?>
            <button type="button" class="mp-pdf-viewer-btn" data-pdf-viewer-src="<?= rex_escape($pdfViewerUrl) ?>" data-pdf-viewer-caption="<?= rex_escape($info['title'] !== '' ? $info['title'] : $filename) ?>" title="<?= rex_escape($this->i18n('mediaplace_open_pdf_viewer')) ?>"><i class="fa-solid fa-book-open"></i></button>
        <?php endif; ?>
        <?php if ('' !== $pdfThumb): ?>
            <img src="<?= rex_escape($pdfThumb) ?>" alt="<?= rex_escape($info['title'] !== '' ? $info['title'] : $filename) ?>" data-fallback-icon="<?= rex_escape(DetailPanelFormatter::fileIconClass($filename)) ?>"<?= '' !== $pdfViewerUrl ? ' class="mp-pdf-viewer-thumb" data-pdf-viewer-src="' . rex_escape($pdfViewerUrl) . '" data-pdf-viewer-caption="' . rex_escape($info['title'] !== '' ? $info['title'] : $filename) . '"' : '' ?>>
        <?php else: ?>
            <i class="<?= rex_escape(DetailPanelFormatter::fileIconClass($filename)) ?>"></i>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="mp-detail-preview mp-detail-preview-icon">
        <i class="<?= rex_escape(DetailPanelFormatter::fileIconClass($filename)) ?>"></i>
    </div>
<?php endif; ?>
