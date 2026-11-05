<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use FriendsOfRedaxo\Mediaplace\AiAltFailures;
use FriendsOfRedaxo\Mediaplace\AiAltTextService;
use FriendsOfRedaxo\Mediaplace\AiImagePreparer;
use FriendsOfRedaxo\Mediaplace\AiAltTextWriter;
use FriendsOfRedaxo\Mediaplace\AltTextStatus;
use FriendsOfRedaxo\Mediaplace\MediaPermission;

/**
 * Vollstaendige, automatische ALT-Text-Vervollstaendigung fuer die Admin-Seite
 * "ALT-Texte vervollstaendigen" (pages/ai_alt_complete.php) -- Ergaenzung zum
 * bestehenden "AI Bulk Management"-Panel im Overlay (Api\AiAltBulk.php), das
 * bewusst auf einen Review-Schritt und ein kleines RUN_LIMIT pro Sitzung
 * ausgelegt ist (Redakteur prueft/bearbeitet jeden Vorschlag). Hier dagegen:
 * OHNE Review direkt schreiben (generate+write in einem Rutsch), damit der
 * gesamte fehlende Bestand in einem Lauf abgearbeitet werden kann -- sowohl
 * im Browser (Chunk-Schleife, siehe pages/ai_alt_complete.php, Tab muss offen
 * bleiben) als auch automatisiert (siehe AiAltCompleteCronjob.php).
 *
 * Nur aktiv, wenn der Admin das explizit per eigenem Schalter
 * (enable_ai_alt_auto_complete) aktiviert hat -- das ist eine bewusste
 * Entscheidung fuer "automatisch schreiben, ohne Vorschau", siehe
 * pages/settings.php.
 *
 * POST (JSON body, {action: ...}):
 * - count             {} -> Gesamtanzahl fehlender ALT-Texte (fuer die Fortschrittsanzeige)
 * - complete_batch    {limit, exclude} -> generiert UND schreibt bis zu "limit"
 *   Dateien, gibt {succeeded, errors, processed, remaining} zurueck
 * - start_background  {} -> startet Command\AiAltComplete als abgekoppelten
 *   Hintergrundprozess (shell_exec(), gleiches Prinzip wie
 *   Api\ThumbWarmup::handleStartBackground(), nur "php bin/console" statt
 *   "wget" als Arbeitspferd) -- laeuft weiter, auch wenn der Browser-Tab
 *   geschlossen wird
 * - background_status {} -> Fortschritt des laufenden Hintergrundprozesses
 * - stop_background   {} -> signalisiert dem Hintergrundprozess, nach der
 *   aktuellen Datei abzubrechen (Stop-Flag-Datei, kein Prozess-Kill noetig)
 */
class AiAltComplete extends \rex_api_function
{
    protected $published = true;

    // Wie AiAltBulk::BATCH_LIMIT_DEFAULT/-MAX: jeder Eintrag ist ein echter
    // KI-Vendor-Call, kleine Chunks halten einen einzelnen Request kurz genug
    // fuer normale PHP-/Webserver-Timeouts.
    private const BATCH_LIMIT_DEFAULT = 10;
    private const BATCH_LIMIT_MAX = 25;

    public function execute(): \rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            $this->send(['success' => false, 'error' => 'Nicht angemeldet.'], 401);
        }
        // Eigene Admin-Seite (siehe package.yml, perm: admin) -- konsistent
        // dazu volle Admin-Pruefung statt der sonst ueblichen
        // MediaPermission-Checks, analog Api\ThumbWarmup.
        if (!\rex::getUser()->isAdmin()) {
            $this->send(['success' => false, 'error' => 'Keine Berechtigung.'], 403);
        }
        if (!self::isEnabled()) {
            $this->send(['success' => false, 'error' => 'Automatische ALT-Text-Vervollständigung ist nicht aktiviert.'], 403);
        }
        if (!AiAltTextService::isAvailable()) {
            $this->send(['success' => false, 'error' => 'KI-Alt-Text-Generierung ist nicht aktiviert oder ai_platform nicht verfügbar.'], 403);
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        $action = (string) ($body['action'] ?? \rex_request('action', 'string', ''));

        switch ($action) {
            case 'count':
                $this->handleCount();

                break;
            case 'complete_batch':
                $this->handleCompleteBatch($body);

                break;
            case 'start_background':
                $this->handleStartBackground();

                break;
            case 'background_status':
                $this->handleBackgroundStatus();

                break;
            case 'stop_background':
                $this->handleStopBackground();

                break;
            default:
                $this->send(['success' => false, 'error' => 'Unbekannte Aktion.'], 400);
        }

        return new \rex_api_result(true);
    }

    public static function isEnabled(): bool
    {
        return (bool) \rex_config::get('mediaplace', 'enable_ai_alt_auto_complete', false);
    }

    private function handleCount(): void
    {
        $this->send([
            'success' => true,
            'total' => count($this->accessibleEligibleFilenames()),
        ]);
    }

    /**
     * Generiert UND schreibt sofort (kein Review-Zwischenschritt) -- das ist
     * der zentrale Unterschied zu AiAltBulk::handleGenerateBatch(). "exclude"
     * traegt wie dort bereits verarbeitete Dateinamen (erfolgreich ODER
     * fehlgeschlagen), sonst waere der Endpunkt zustandslos.
     *
     * @param array<string, mixed> $body
     */
    private function handleCompleteBatch(array $body): void
    {
        $limit = max(1, min(self::BATCH_LIMIT_MAX, (int) ($body['limit'] ?? self::BATCH_LIMIT_DEFAULT)));
        $excludeRaw = is_array($body['exclude'] ?? null) ? $body['exclude'] : [];
        $exclude = array_flip(array_map('strval', $excludeRaw));

        $eligible = array_values(array_filter(
            $this->accessibleEligibleFilenames(),
            static function (string $filename) use ($exclude): bool {
                return !isset($exclude[$filename]);
            },
        ));
        $batch = array_slice($eligible, 0, $limit);

        $service = new AiAltTextService();
        $clangIds = AiAltTextWriter::resolveClangIds();
        $succeeded = 0;
        $errors = [];

        foreach ($batch as $filename) {
            $media = \rex_media::get($filename);
            if (!$media) {
                $errors[] = ['filename' => $filename, 'message' => 'Datei "' . \rex_escape($filename) . '" nicht gefunden.'];

                continue;
            }
            try {
                $texts = $service->generateAltText($media, $clangIds);
                if ([] === $texts) {
                    $errors[] = ['filename' => $filename, 'message' => 'Keine verwertbare Antwort für "' . \rex_escape($filename) . '" erhalten.'];
                    AiAltFailures::mark($filename, 'Keine verwertbare Antwort der KI erhalten.');

                    continue;
                }
                AiAltTextWriter::write($media, $texts);
                AiAltFailures::clear($filename);
                ++$succeeded;
            } catch (\Throwable $e) {
                $errors[] = ['filename' => $filename, 'message' => \rex_escape($filename) . ': ' . $e->getMessage()];
                AiAltFailures::mark($filename, $e->getMessage());
            }
        }

        $processed = count($batch);
        $remaining = max(0, count($eligible) - $processed);

        // Alle in diesem Batch angefassten Dateien (erfolgreich UND
        // fehlgeschlagen) an den Client zurueckmelden, damit er sie beim
        // naechsten Aufruf in "exclude" mitschickt -- fehlgeschlagene Dateien
        // bleiben sonst dauerhaft in getFilenamesMissingAlt() stehen und
        // wuerden ohne "exclude" bei jedem Batch erneut (und erneut
        // erfolglos) versucht, statt zu den naechsten Dateien weiterzugehen.
        $this->send(['success' => true, 'succeeded' => $succeeded, 'errors' => $errors, 'processed' => $processed, 'remaining' => $remaining, 'processedFilenames' => $batch]);
    }

    /**
     * Wie AiAltBulk::accessibleEligibleFilenames() -- Dateien ohne ALT-Text,
     * zugaenglich fuer den aktuellen Nutzer, ohne SVGs (siehe dortiger
     * Docblock). Bewusst keine gemeinsame Basisklasse fuer diese kleine
     * Methode -- beide Endpunkte sollen unabhaengig voneinander aenderbar
     * bleiben.
     *
     * @return list<string>
     */
    private function accessibleEligibleFilenames(): array
    {
        $filenames = AiAltFailures::withoutMarked(array_values(array_filter(
            AltTextStatus::getFilenamesMissingAlt(),
            static function (string $filename): bool {
                return 'svg' !== strtolower(pathinfo($filename, PATHINFO_EXTENSION)) && AiImagePreparer::canPrepare($filename);
            },
        )));

        if (MediaPermission::hasFullAccess()) {
            return $filenames;
        }

        $accessibleCats = array_flip(MediaPermission::getAccessibleCategoryIds());

        return array_values(array_filter($filenames, static function (string $filename) use ($accessibleCats): bool {
            $media = \rex_media::get($filename);

            return $media && isset($accessibleCats[$media->getCategoryId()]);
        }));
    }

    /**
     * Startet Command\AiAltComplete (siehe dortiger Docblock) als
     * abgekoppelten Hintergrundprozess ueber "php bin/console" --
     * shell_exec() statt eines eigenen Prozess-Management-Systems, gleiches
     * Prinzip wie Api\ThumbWarmup::handleStartBackground().
     */
    private function handleStartBackground(): void
    {
        if (!self::shellExecAvailable()) {
            $this->send(['success' => false, 'error' => 'shell_exec ist auf diesem Server nicht verfuegbar.'], 400);

            return;
        }
        if (self::isBackgroundRunning()) {
            $this->send(['success' => true, 'already_running' => true]);

            return;
        }

        $console = \rex_path::bin('console');
        $bgLogFile = self::stateFile('background.stdout.log');
        $pidFile = self::stateFile('background.pid');

        \rex_file::delete(self::stateFile('background.done'));
        \rex_file::delete(self::stateFile('background.stop'));
        \rex_file::delete(self::stateFile('background.log'));

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($console) . ' mediaplace:ai-alt-complete --no-interaction';
        shell_exec('(' . $command . ') > ' . escapeshellarg($bgLogFile) . ' 2>&1 & echo $! > ' . escapeshellarg($pidFile));

        $this->send(['success' => true, 'started' => true]);
    }

    /**
     * Fortschritt kommt direkt aus der von Command\AiAltComplete
     * geschriebenen JSON-Statusdatei (siehe dortiger Docblock) -- anders als
     * bei Api\ThumbWarmup (Zeilen zaehlen) gibt es hier bereits fertige
     * Zaehlerstaende, kein eigener Abgleich noetig.
     */
    private function handleBackgroundStatus(): void
    {
        $progressFile = self::stateFile('background.log');
        $doneFile = self::stateFile('background.done');

        if (!is_file($progressFile)) {
            $this->send(['success' => true, 'running' => false, 'processed' => 0, 'succeeded' => 0, 'errors' => 0, 'total' => 0, 'done' => true, 'last_filename' => null]);

            return;
        }

        $status = json_decode(\rex_file::get($progressFile, '{}'), true);
        $status = is_array($status) ? $status : [];

        $this->send([
            'success' => true,
            'running' => !is_file($doneFile),
            'processed' => (int) ($status['processed'] ?? 0),
            'succeeded' => (int) ($status['succeeded'] ?? 0),
            'errors' => (int) ($status['errors'] ?? 0),
            'total' => (int) ($status['total'] ?? 0),
            'done' => is_file($doneFile),
            'last_filename' => $status['last_filename'] ?? null,
        ]);
    }

    /**
     * Best effort, wie Api\ThumbWarmup::handleStopBackground(): der laufende
     * Command\AiAltComplete-Prozess prueft die Stop-Flag-Datei nur zwischen
     * zwei Dateien (kein harter Kill mitten in einem KI-API-Call).
     */
    private function handleStopBackground(): void
    {
        \rex_file::put(self::stateFile('background.stop'), '1');

        $this->send(['success' => true, 'stopped' => true]);
    }

    private static function isBackgroundRunning(): bool
    {
        $progressFile = self::stateFile('background.log');
        $doneFile = self::stateFile('background.done');

        return is_file($progressFile) && !is_file($doneFile);
    }

    private static function shellExecAvailable(): bool
    {
        return function_exists('shell_exec')
            && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
    }

    private static function stateFile(string $name): string
    {
        return \rex_path::addonData('mediaplace', 'ai_alt_complete_' . $name);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(array $data, int $status = 200): never
    {
        \rex_response::cleanOutputBuffers();
        if (200 !== $status) {
            http_response_code($status);
        }
        \rex_response::sendJson($data);
        exit;
    }
}
