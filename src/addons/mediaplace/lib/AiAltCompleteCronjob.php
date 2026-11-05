<?php

namespace FriendsOfRedaxo\Mediaplace;

use rex_cronjob;
use rex_i18n;
use rex_media;

/**
 * Automatisches, periodisches Nachziehen fehlender ALT-Texte per KI --
 * Hintergrund-Pendant zur manuellen Admin-Seite (pages/ai_alt_complete.php,
 * Browser-Chunk-Modus mit offenem Tab). Schreibt OHNE Review direkt (siehe
 * AiAltTextWriter::write()) -- es ist niemand da, der die Vorschlaege vorher
 * pruefen koennte. Nur aktiv, wenn der Admin das bewusst per eigenem Schalter
 * freigeschaltet hat (Api\AiAltComplete::isEnabled(), siehe
 * pages/settings.php "Automatisch schreiben, ohne Vorschau"-Hinweis dort).
 *
 * Pro Lauf wird nur ein begrenztes Kontingent bearbeitet (getParamFields()),
 * der Rest folgt beim naechsten planmaessigen Lauf -- gleiches Prinzip wie
 * ThumbWarmupCronjob.
 */
class AiAltCompleteCronjob extends rex_cronjob
{
    public function execute(): bool
    {
        if (!Api\AiAltComplete::isEnabled()) {
            $this->setMessage(rex_i18n::msg('mediaplace_cronjob_ai_alt_complete_disabled'));

            return true;
        }
        if (!AiAltTextService::isAvailable()) {
            $this->setMessage(rex_i18n::msg('mediaplace_cronjob_ai_alt_complete_unavailable'));

            return true;
        }

        $batchSize = max(1, (int) $this->getParam('batch_size', 20));

        // ohne SVG, nur aufbereitbare Formate, ohne früher gescheiterte Dateien (AiAltFailures) – sonst blockieren
        // dauerhaft scheiternde Dateien am Anfang der Liste jeden Lauf
        $filenames = AiAltFailures::withoutMarked(array_values(array_filter(
            AltTextStatus::getFilenamesMissingAlt(),
            static function (string $filename): bool {
                return 'svg' !== strtolower(pathinfo($filename, PATHINFO_EXTENSION)) && AiImagePreparer::canPrepare($filename);
            },
        )));
        $batch = array_slice($filenames, 0, $batchSize);

        $service = new AiAltTextService();
        $clangIds = AiAltTextWriter::resolveClangIds();
        $succeeded = 0;
        $failed = 0;

        foreach ($batch as $filename) {
            $media = rex_media::get($filename);
            if (!$media) {
                ++$failed;

                continue;
            }
            try {
                $texts = $service->generateAltText($media, $clangIds);
                if ([] === $texts) {
                    ++$failed;
                    AiAltFailures::mark($filename, 'Keine verwertbare Antwort der KI erhalten.');

                    continue;
                }
                AiAltTextWriter::write($media, $texts);
                AiAltFailures::clear($filename);
                ++$succeeded;
            } catch (\Throwable $e) {
                ++$failed;
                AiAltFailures::mark($filename, $e->getMessage());
            }
        }

        $this->setMessage(rex_i18n::msg(
            'mediaplace_cronjob_ai_alt_complete_result',
            $succeeded,
            count($batch),
            $failed,
        ));

        return true;
    }

    public function getTypeName(): string
    {
        return rex_i18n::msg('mediaplace_cronjob_ai_alt_complete_title');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getParamFields(): array
    {
        return [
            [
                'label' => rex_i18n::msg('mediaplace_cronjob_ai_alt_complete_batch_size'),
                'name' => 'batch_size',
                'type' => 'select',
                'default' => 20,
                'options' => [
                    5 => 5,
                    10 => 10,
                    20 => 20,
                    50 => 50,
                    100 => 100,
                ],
            ],
        ];
    }
}
