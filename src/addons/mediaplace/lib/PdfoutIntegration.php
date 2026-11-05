<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Konsumiert das optionale "pdfout"-Addon (FriendsOfRedaxo/pdfout):
 *
 * 1. PDF-Vorschau (Grid + Detail-Panel): eigener Media-Manager-Typ PDF_THUMB_TYPE mit pdfout's Effekt
 *    "pdf_thumbnail" (erste Seite als JPG) und anschliessendem Resize. Lazy zur Boot-Zeit angelegt,
 *    gleiches Muster wie FfmpegIntegration::ensureVideoThumbTypes().
 * 2. PDF-Viewer im Detail-Panel: pdfout's pdf.js-Viewer (PdfOut::viewer()) in einem Overlay.
 *
 * pdfout-Klassen nur vollqualifiziert innerhalb abgesicherter Methoden -- die Datei muss auch ohne
 * pdfout ladbar sein.
 */
class PdfoutIntegration
{
    public const PDF_THUMB_TYPE = 'mediaplace_pdf_thumb';

    /** @var list<string> */
    public const EXTENSIONS = ['pdf'];

    /** Cache-Dauer fuer die Konverter-Pruefung, siehe isConverterAvailable() */
    private const CONVERTER_CHECK_TTL = 3600;

    public static function isPdfoutAvailable(): bool
    {
        return \rex_addon::get('pdfout')->isAvailable() && class_exists(\FriendsOfRedaxo\PdfOut\PdfOut::class);
    }

    /**
     * Vorschau nur, wenn pdfout einen Konverter findet (poppler-utils, Ghostscript oder Imagick). Sonst
     * wuerde das Grid Bilder anfordern, die serverseitig scheitern.
     */
    public static function isThumbAvailable(): bool
    {
        return self::isPdfoutAvailable()
            && \rex_addon::get('media_manager')->isAvailable()
            && class_exists(\rex_effect_pdf_thumbnail::class)
            && class_exists(\FriendsOfRedaxo\PdfOut\PdfThumbnail::class)
            && self::isConverterAvailable();
    }

    /**
     * PdfThumbnail::getDetectedTool() sucht die Programme bei jedem Request neu -- Ergebnis wie bei
     * FfmpegIntegration fuer CONVERTER_CHECK_TTL Sekunden in rex_config.
     */
    private static function isConverterAvailable(): bool
    {
        $cached = \rex_config::get('mediaplace', 'pdf_converter_check');
        if (is_array($cached) && isset($cached['checked_at'], $cached['tool']) && (time() - (int) $cached['checked_at']) < self::CONVERTER_CHECK_TTL) {
            return 'none' !== $cached['tool'];
        }

        $tool = \FriendsOfRedaxo\PdfOut\PdfThumbnail::getDetectedTool();
        \rex_config::set('mediaplace', 'pdf_converter_check', ['checked_at' => time(), 'tool' => $tool]);

        return 'none' !== $tool;
    }

    /** Media-Manager-Typ fuer Grid/Vorwaermen, null ohne pdfout/Konverter */
    public static function getActiveThumbType(): ?string
    {
        return self::isThumbAvailable() ? self::PDF_THUMB_TYPE : null;
    }

    /**
     * URL des pdf.js-Viewers fuer eine Mediendatei, null ohne pdfout.
     */
    public static function viewerUrl(string $filename, ?string $updatedate, int $filesize): ?string
    {
        if (!self::isPdfoutAvailable() || !DetailPanelFormatter::isPdfFilename($filename)) {
            return null;
        }

        // Der Viewer liegt unter assets/addons/pdfout/... und loest "file" relativ zu sich selbst auf:
        // deshalb wurzel-relativ statt des relativen Backend-Pfads ("../media/...").
        $file = self::rootRelative(DetailPanelFormatter::mediaFileUrl($filename, $updatedate, $filesize));

        return \FriendsOfRedaxo\PdfOut\PdfOut::viewer($file);
    }

    /**
     * Legt PDF_THUMB_TYPE an, falls er fehlt (z.B. weil pdfout erst nach MediaPlace installiert wurde).
     * Idempotent, billige Existenzpruefung.
     */
    public static function ensurePdfThumbType(): void
    {
        if (!self::isThumbAvailable()) {
            return;
        }

        $sql = \rex_sql::factory();
        $existing = $sql->getArray(
            'SELECT id FROM ' . \rex::getTable('media_manager_type') . ' WHERE name = :name',
            [':name' => self::PDF_THUMB_TYPE],
        );
        if (!empty($existing)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $sql->setTable(\rex::getTable('media_manager_type'));
        $sql->setValue('status', 1);
        $sql->setValue('name', self::PDF_THUMB_TYPE);
        $sql->setValue('description', 'MediaPlace – PDF-Vorschau, erste Seite (pdfout)');
        $sql->setValue('createdate', $now);
        $sql->setValue('createuser', 'mediaplace');
        $sql->setValue('updatedate', $now);
        $sql->setValue('updateuser', 'mediaplace');
        $sql->insert();
        $typeId = (int) $sql->getLastId();

        $effects = [
            'pdf_thumbnail' => [
                'rex_effect_pdf_thumbnail' => [
                    'rex_effect_pdf_thumbnail_convert_to' => 'jpg',
                    'rex_effect_pdf_thumbnail_density' => '100',
                    'rex_effect_pdf_thumbnail_quality' => '80',
                    'rex_effect_pdf_thumbnail_page' => '1',
                    'rex_effect_pdf_thumbnail_color' => 'ffffff',
                ],
            ],
            'resize' => [
                'rex_effect_resize' => [
                    'rex_effect_resize_width' => '600',
                    'rex_effect_resize_height' => '600',
                    'rex_effect_resize_style' => 'maximum',
                    'rex_effect_resize_allow_enlarge' => 'not_enlarge',
                ],
            ],
        ];
        $priority = 0;
        foreach ($effects as $effect => $params) {
            $effectSql = \rex_sql::factory();
            $effectSql->setTable(\rex::getTable('media_manager_type_effect'));
            $effectSql->setValue('type_id', $typeId);
            $effectSql->setValue('effect', $effect);
            $effectSql->setValue('parameters', json_encode($params));
            $effectSql->setValue('priority', ++$priority);
            $effectSql->setValue('createdate', $now);
            $effectSql->setValue('createuser', 'mediaplace');
            $effectSql->setValue('updatedate', $now);
            $effectSql->setValue('updateuser', 'mediaplace');
            $effectSql->insert();
        }
    }

    /**
     * "../media/x.pdf" (relativ zum aufgerufenen Skript) -> "/media/x.pdf", auch in Unterordnern.
     */
    private static function rootRelative(string $url): string
    {
        if (preg_match('#^(/|[a-z][a-z0-9+.-]*://)#i', $url)) {
            return $url;
        }

        $script = str_replace('\\', '/', \rex_request::server('SCRIPT_NAME', 'string', '/index.php'));
        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);
        $segments = [];
        foreach (explode('/', dirname($script) . '/' . $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments) . (null !== $query ? '?' . $query : '');
    }
}
