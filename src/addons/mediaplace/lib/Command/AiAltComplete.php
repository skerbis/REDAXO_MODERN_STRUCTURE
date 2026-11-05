<?php

namespace FriendsOfRedaxo\Mediaplace\Command;

use FriendsOfRedaxo\Mediaplace\AiAltFailures;
use FriendsOfRedaxo\Mediaplace\AiAltTextService;
use FriendsOfRedaxo\Mediaplace\AiImagePreparer;
use FriendsOfRedaxo\Mediaplace\AiAltTextWriter;
use FriendsOfRedaxo\Mediaplace\AltTextStatus;
use FriendsOfRedaxo\Mediaplace\Api\AiAltComplete as AiAltCompleteApi;
use rex_console_command;
use rex_file;
use rex_media;
use rex_path;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Konsolen-Pendant zu AiAltCompleteCronjob: arbeitet den KOMPLETTEN
 * fehlenden Bestand in einem Rutsch ab (kein festes Kontingent pro Lauf),
 * schreibt OHNE Review direkt (siehe AiAltTextWriter::write()). Wird von
 * Api\AiAltComplete::handleStartBackground() per shell_exec() als
 * abgekoppelter Hintergrundprozess gestartet (gleiches Prinzip wie
 * Api\ThumbWarmup, nur mit "php bin/console" statt "wget" als
 * Arbeitspferd -- eine ALT-Text-Generierung braucht echte PHP-Logik
 * (KI-API-Call, DB-Schreiben), kein reiner HTTP-GET).
 *
 * Fortschritt wird nach JEDER Datei (nicht erst am Ende) in eine eigene
 * Statusdatei geschrieben, die Api\AiAltComplete::handleBackgroundStatus()
 * ausliest -- analog Api\ThumbWarmup's progress.log. Ein Stop wird über
 * eine eigene Flag-Datei signalisiert (kein Prozess-Kill noetig, da wget
 * bei ThumbWarmup mangels PHP-Zustand anders funktioniert als hier).
 */
class AiAltComplete extends rex_console_command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Vervollstaendigt fehlende ALT-Texte per KI, ohne Review-Schritt (siehe MediaPlace-Einstellungen)')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Anzahl Dateien pro DB-Batch (nur intern, kein Limit fuer den Gesamtlauf)', 20)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $progressFile = self::stateFile('background.log');
        $stopFile = self::stateFile('background.stop');
        $doneFile = self::stateFile('background.done');

        rex_file::delete($doneFile);
        rex_file::delete($stopFile);
        rex_file::put($progressFile, json_encode(['processed' => 0, 'succeeded' => 0, 'errors' => 0, 'total' => 0, 'last_filename' => null]));

        if (!AiAltCompleteApi::isEnabled()) {
            $io->error('Automatische ALT-Text-Vervollstaendigung ist nicht aktiviert.');
            rex_file::put($doneFile, '1');

            return 1;
        }
        if (!AiAltTextService::isAvailable()) {
            $io->error('KI-Alt-Text-Generierung ist nicht aktiviert oder ai_platform nicht verfuegbar.');
            rex_file::put($doneFile, '1');

            return 1;
        }

        // ohne SVG, nur aufbereitbare Formate, ohne früher gescheiterte Dateien (AiAltFailures) – sonst blockieren
        // dauerhaft scheiternde Dateien am Anfang der Liste jeden Lauf
        $filenames = AiAltFailures::withoutMarked(array_values(array_filter(
            AltTextStatus::getFilenamesMissingAlt(),
            static function (string $filename): bool {
                return 'svg' !== strtolower(pathinfo($filename, PATHINFO_EXTENSION)) && AiImagePreparer::canPrepare($filename);
            },
        )));
        $total = count($filenames);

        $service = new AiAltTextService();
        $clangIds = AiAltTextWriter::resolveClangIds();
        $processed = 0;
        $succeeded = 0;
        $errors = 0;

        $io->title('MediaPlace: ALT-Texte vervollstaendigen (Hintergrund)');
        $io->writeln($total . ' Datei(en) ohne ALT-Text gefunden.');

        foreach ($filenames as $filename) {
            if (is_file($stopFile)) {
                $io->warning('Abgebrochen per Stop-Signal.');

                break;
            }

            $media = rex_media::get($filename);
            if ($media) {
                try {
                    $texts = $service->generateAltText($media, $clangIds);
                    if ([] !== $texts) {
                        AiAltTextWriter::write($media, $texts);
                        AiAltFailures::clear($filename);
                        ++$succeeded;
                        $io->writeln('OK: ' . $filename);
                    } else {
                        ++$errors;
                        AiAltFailures::mark($filename, 'Keine verwertbare Antwort der KI erhalten.');
                        $io->writeln('FEHLER (keine Antwort): ' . $filename);
                    }
                } catch (\Throwable $e) {
                    ++$errors;
                    AiAltFailures::mark($filename, $e->getMessage());
                    $io->writeln('FEHLER: ' . $filename . ' (' . $e->getMessage() . ')');
                }
            } else {
                ++$errors;
            }

            ++$processed;
            rex_file::put($progressFile, json_encode([
                'processed' => $processed,
                'succeeded' => $succeeded,
                'errors' => $errors,
                'total' => $total,
                'last_filename' => $filename,
            ]));
        }

        rex_file::delete($stopFile);
        rex_file::put($doneFile, '1');

        $io->success(sprintf('Fertig: %d verarbeitet, %d erfolgreich, %d Fehler.', $processed, $succeeded, $errors));

        return 0;
    }

    private static function stateFile(string $name): string
    {
        return rex_path::addonData('mediaplace', 'ai_alt_complete_' . $name);
    }
}
