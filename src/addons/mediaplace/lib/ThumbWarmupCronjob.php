<?php

namespace FriendsOfRedaxo\Mediaplace;

use rex;
use rex_cronjob;
use rex_i18n;
use rex_media_manager;
use rex_sql;

/**
 * Erzeugt Grid-Vorschaubilder vorab im Hintergrund, statt sie ausschliesslich
 * "on demand" beim ersten Betrachten zu generieren -- bei vielen gleichzeitig
 * sichtbaren Dateien (grosse Kategorie, breites Grid) waere das fuer einen
 * normalen (Shared-)Webserver zu viel gleichzeitige Last. Deckt beide eigenen
 * Grid-Vorschau-Typen ab: den Bild-Typ "mediaplace_thumb" (siehe install.php,
 * immer verfuegbar) und den je nach Einstellungen aktiven Video-Typ (animiert
 * oder Standbild, siehe FfmpegIntegration::getActiveVideoThumbType()), nur
 * wenn ffmpeg installiert UND lauffaehig ist UND die Video-Vorschau nicht auf
 * "aus" steht -- Video-Generierung ist ungleich teurer als ein Bild-Resize,
 * deshalb pro Typ ein eigenes, unterschiedlich hoch vorbelegtes Kontingent.
 * Dazu die PDF-Vorschau (PdfoutIntegration), nur mit pdfout und PDF-Konverter.
 *
 * Pro Lauf wird je Typ nur eine begrenzte Anzahl NEUER Vorschaubilder erzeugt,
 * der Rest folgt beim naechsten planmaessigen Lauf -- bereits gecachte Dateien
 * werden dabei uebersprungen (rex_media_manager::create() erkennt einen
 * vorhandenen, aktuellen Cache-Eintrag selbst und ueberspringt die eigentliche
 * Konvertierung dafuer; das ist billig genug, um es nicht separat vorab zu
 * pruefen -- ein einzelner create()-Aufruf gilt erst ab einer messbaren
 * Mindestdauer als "hat wirklich neu generiert", siehe SLOW_THRESHOLD_*).
 * Neueste Dateien zuerst, damit frisch hochgeladene Medien moeglichst schnell
 * eine Vorschau bekommen statt hinter einem grossen, laengst durchgewaermten
 * Bestand zu warten.
 */
class ThumbWarmupCronjob extends rex_cronjob
{
    /** Auch von Api\ThumbWarmup genutzt (manuelles Vorwaermen mit Fortschrittsanzeige) -- ein Typ/eine Extension-Liste, nicht zweimal pflegen. */
    public const IMAGE_TYPE = 'mediaplace_thumb';

    /** Deckungsgleich mit isImage() in assets/mediaplace-helpers.js (ohne "ico", siehe DetailPanelFormatter::isImageFilename()). */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp'];

    /** Ein Bild-Resize ist deutlich billiger als eine Video-Konvertierung -- niedrigerer Schwellwert. */
    private const IMAGE_SLOW_THRESHOLD_SECONDS = 0.05;

    /** Ab dieser Dauer gilt ein einzelner create()-Aufruf als "hat wirklich neu generiert" statt als Cache-Treffer. */
    private const VIDEO_SLOW_THRESHOLD_SECONDS = 0.3;

    /** Einfache PDFs rendert Ghostscript/Poppler oft in unter 0,1 s -- Schwelle wie bei Bildern. */
    private const PDF_SLOW_THRESHOLD_SECONDS = 0.05;

    /** Harte Obergrenze an ueberhaupt geprueften Dateien pro Lauf und Typ (auch bei lauter Cache-Treffern). */
    private const MAX_SCANNED_PER_RUN = 300;

    public function execute(): bool
    {
        $imageBatchSize = max(1, (int) $this->getParam('image_batch_size', 20));
        $imageResult = $this->warmupType(self::IMAGE_TYPE, self::IMAGE_EXTENSIONS, $imageBatchSize, self::IMAGE_SLOW_THRESHOLD_SECONDS);

        // Respektiert die Einstellungen (aus/Standbild/animiert) -- bei "aus"
        // ist getActiveVideoThumbType() null, dann wird gar nichts angefasst.
        $videoResult = ['warmed' => 0, 'scanned' => 0];
        $activeVideoThumbType = FfmpegIntegration::getActiveVideoThumbType();
        if (FfmpegIntegration::isAvailable() && null !== $activeVideoThumbType) {
            $videoBatchSize = max(1, (int) $this->getParam('video_batch_size', 5));
            $videoResult = $this->warmupType($activeVideoThumbType, FfmpegIntegration::supportedExtensions(), $videoBatchSize, self::VIDEO_SLOW_THRESHOLD_SECONDS);
        }

        // pdfout-Integration: nur mit pdfout und einem PDF-Konverter
        $pdfResult = ['warmed' => 0, 'scanned' => 0];
        $pdfThumbType = PdfoutIntegration::getActiveThumbType();
        if (null !== $pdfThumbType) {
            $pdfBatchSize = max(1, (int) $this->getParam('pdf_batch_size', 10));
            $pdfResult = $this->warmupType($pdfThumbType, PdfoutIntegration::EXTENSIONS, $pdfBatchSize, self::PDF_SLOW_THRESHOLD_SECONDS);
        }

        $this->setMessage(rex_i18n::msg(
            'mediaplace_cronjob_thumb_warmup_result',
            $imageResult['warmed'],
            $imageResult['scanned'],
            $videoResult['warmed'],
            $videoResult['scanned'],
            $pdfResult['warmed'],
            $pdfResult['scanned'],
        ));

        return true;
    }

    /**
     * @param list<string> $extensions
     * @return array{warmed: int, scanned: int}
     */
    private function warmupType(string $type, array $extensions, int $batchSize, float $slowThreshold): array
    {
        $sql = rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($extensions), '?'));
        $sql->setQuery(
            'SELECT filename FROM ' . rex::getTable('media') . '
             WHERE LOWER(SUBSTRING_INDEX(filename, \'.\', -1)) IN (' . $placeholders . ')
             ORDER BY createdate DESC
             LIMIT ' . self::MAX_SCANNED_PER_RUN,
            $extensions,
        );

        $warmed = 0;
        $scanned = 0;

        foreach ($sql as $row) {
            ++$scanned;
            $filename = (string) $row->getValue('filename');

            $start = microtime(true);
            // create() erwartet nur den Dateinamen, nicht den vollen Pfad --
            // ruft rex_path::media() intern selbst auf.
            rex_media_manager::create($type, $filename);
            $elapsed = microtime(true) - $start;

            if ($elapsed >= $slowThreshold) {
                ++$warmed;
                if ($warmed >= $batchSize) {
                    break;
                }
            }
        }

        return ['warmed' => $warmed, 'scanned' => $scanned];
    }

    public function getTypeName(): string
    {
        return rex_i18n::msg('mediaplace_cronjob_thumb_warmup_title');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getParamFields(): array
    {
        return [
            [
                'label' => rex_i18n::msg('mediaplace_cronjob_thumb_warmup_image_batch_size'),
                'name' => 'image_batch_size',
                'type' => 'select',
                'default' => 20,
                'options' => [
                    5 => 5,
                    10 => 10,
                    20 => 20,
                    50 => 50,
                ],
            ],
            [
                'label' => rex_i18n::msg('mediaplace_cronjob_thumb_warmup_video_batch_size'),
                'name' => 'video_batch_size',
                'type' => 'select',
                'default' => 5,
                'options' => [
                    2 => 2,
                    5 => 5,
                    10 => 10,
                    20 => 20,
                ],
            ],
            [
                'label' => rex_i18n::msg('mediaplace_cronjob_thumb_warmup_pdf_batch_size'),
                'name' => 'pdf_batch_size',
                'type' => 'select',
                'default' => 10,
                'options' => [
                    5 => 5,
                    10 => 10,
                    20 => 20,
                    50 => 50,
                ],
            ],
        ];
    }
}
