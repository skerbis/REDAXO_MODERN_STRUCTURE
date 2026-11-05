<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use FriendsOfRedaxo\Mediaplace\FfmpegIntegration;
use FriendsOfRedaxo\Mediaplace\PdfoutIntegration;
use FriendsOfRedaxo\Mediaplace\ThumbWarmupCronjob;
use rex_api_function;
use rex_api_result;

/**
 * Manuelles Vorwaermen ALLER Grid-Vorschaubilder mit Fortschrittsanzeige
 * (pages/thumb_warmup.php) -- Ergaenzung zum bestehenden ThumbWarmupCronjob,
 * der nur in kleinen, periodischen Haeppchen die NEUESTEN Dateien nachzieht
 * (fuer den taeglichen Betrieb gedacht, keine Fortschritts-/Vollstaendigkeits-
 * Garantie). Hier dagegen: einmalig den KOMPLETTEN Bestand systematisch nach
 * ID durchpaginieren, bis wirklich jede Datei einmal angefasst wurde -- fuer
 * den Fall "grosser, kalter Bestand nach Migration/Erstinstallation, jetzt
 * einmal komplett durchwaermen statt auf viele Cron-Laeufe zu warten".
 *
 * rex_media_manager::create() erkennt einen vorhandenen, aktuellen Cache-
 * Eintrag selbst und ueberspringt die eigentliche Konvertierung dafuer (billig
 * genug, um es nicht separat vorab zu pruefen) -- ein wiederholter Aufruf
 * dieses Endpunkts ist damit gefahrlos (kein Doppel-Rendern bereits gecachter
 * Dateien).
 *
 * Bewusst kleine feste Batch-Groesse pro Request (siehe BATCH_SIZE): der
 * Client (thumb_warmup.php) ruft sequentiell, nicht parallel, auf -- genau
 * das soll die vielen gleichzeitigen Media-Manager-Anfragen vermeiden, die
 * auf langsamen Servern beim normalen Lazy-Loading eines grossen, kalten
 * Grids zum Problem werden.
 *
 * POST (JSON body, {action: ...}):
 * - count             {} -> Gesamtanzahl Dateien je Typ (fuer die Fortschrittsanzeige)
 * - warm_batch        {type: 'image'|'video'|'pdf', offset} -> waermt bis zu BATCH_SIZE
 *   Dateien ab offset, gibt {processed, next_offset, total, done} zurueck
 * - start_background  {} -> baut die komplette Liste aller Vorschaubild-URLs
 *   (Bild + ggf. Video, rex_media_manager::getUrl()) und startet sie als
 *   abgekoppelten Hintergrundprozess auf dem Server (xargs+wget ueber
 *   shell_exec(), gleiches Prinzip wie ffmpeg's Api\Converter::handleStart())
 *   -- laeuft weiter, auch wenn der Browser-Tab geschlossen wird
 * - background_status {} -> Fortschritt des laufenden Hintergrundprozesses
 * - stop_background   {} -> beendet den Hintergrundprozess (best effort)
 *
 * Medien-URLs (rex_media_manager::getUrl()) sind OEFFENTLICH erreichbar (kein
 * Login noetig -- derselbe Mechanismus, ueber den Bilder auch normalen
 * Website-Besuchern ausgeliefert werden), wget im Hintergrundprozess braucht
 * also keine Session/Auth. Einzige Voraussetzung: der Server muss seine
 * EIGENE oeffentliche URL erreichen koennen (kein Problem bei den meisten
 * Setups, kann aber hinter bestimmten Reverse-Proxy-/Firewall-
 * Konfigurationen fehlschlagen -- sichtbar am Fortschritt, der bei 0 stehen
 * bleibt).
 */
class ThumbWarmup extends rex_api_function
{
    // Siehe Api\StorageUsage/CategoryBulk-Kommentar: umgeht nur das
    // isBackend()-Gate, execute() prueft Login+Rechte ohnehin selbst.
    protected $published = true;

    private const BATCH_SIZE = 15;

    /** Gleichzeitige wget-Aufrufe im Hintergrundprozess -- niedrig gehalten, das soll ja genau die Server-Last begrenzen. */
    private const BACKGROUND_CONCURRENCY = 4;

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        // Eigene Admin-Seite (siehe package.yml, perm: admin) -- konsistent
        // dazu auch hier volle Admin-Pruefung statt der sonst ueblichen
        // MediaPermission::hasMediaAccess() (die wuerde jedem mit Basis-
        // Medienzugriff erlauben, auf einem fremden Server potenziell
        // teure Massen-Konvertierungen anzustossen).
        $user = \rex::getUser();
        if (!$user->isAdmin()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = [];
        }
        $action = (string) ($body['action'] ?? '');

        switch ($action) {
            case 'count':
                $this->handleCount();
                break;
            case 'warm_batch':
                $this->handleWarmBatch($body);
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
                \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                \rex_response::sendJson(['error' => 'Unknown action']);
        }

        exit;
    }

    private function handleCount(): void
    {
        $videoType = FfmpegIntegration::isAvailable() ? FfmpegIntegration::getActiveVideoThumbType() : null;
        $pdfType = PdfoutIntegration::getActiveThumbType();

        \rex_response::sendJson([
            'image' => ['total' => $this->countForExtensions(ThumbWarmupCronjob::IMAGE_EXTENSIONS)],
            'video' => [
                'total' => null !== $videoType ? $this->countForExtensions(FfmpegIntegration::supportedExtensions()) : 0,
                'active' => null !== $videoType,
            ],
            'pdf' => [
                'total' => null !== $pdfType ? $this->countForExtensions(PdfoutIntegration::EXTENSIONS) : 0,
                'active' => null !== $pdfType,
            ],
        ]);
    }

    /**
     * @param mixed[] $body
     */
    private function handleWarmBatch(array $body): void
    {
        $type = (string) ($body['type'] ?? '');
        $offset = max(0, (int) ($body['offset'] ?? 0));

        if ('image' === $type) {
            $mmType = ThumbWarmupCronjob::IMAGE_TYPE;
            $extensions = ThumbWarmupCronjob::IMAGE_EXTENSIONS;
        } elseif ('video' === $type) {
            $mmType = FfmpegIntegration::isAvailable() ? FfmpegIntegration::getActiveVideoThumbType() : null;
            $extensions = FfmpegIntegration::supportedExtensions();
            if (null === $mmType) {
                \rex_response::sendJson(['processed' => 0, 'next_offset' => $offset, 'total' => 0, 'done' => true]);
                return;
            }
        } elseif ('pdf' === $type) {
            $mmType = PdfoutIntegration::getActiveThumbType();
            $extensions = PdfoutIntegration::EXTENSIONS;
            if (null === $mmType) {
                \rex_response::sendJson(['processed' => 0, 'next_offset' => $offset, 'total' => 0, 'done' => true]);
                return;
            }
        } else {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Unknown type']);
            return;
        }

        $total = $this->countForExtensions($extensions);

        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($extensions), '?'));
        // LIMIT/OFFSET direkt eingebaut statt als Platzhalter (wie schon in
        // ThumbWarmupCronjob::warmupType()) -- beides int-typisiert (BATCH_SIZE
        // Konstante, $offset per (int)-Cast oben), kein Injection-Risiko, aber
        // PDO-Platzhalter fuer LIMIT/OFFSET sind je nach Treiber-Konfiguration
        // nicht zuverlaessig.
        $sql->setQuery(
            'SELECT filename FROM ' . \rex::getTable('media') . '
             WHERE LOWER(SUBSTRING_INDEX(filename, \'.\', -1)) IN (' . $placeholders . ')
             ORDER BY id ASC
             LIMIT ' . self::BATCH_SIZE . ' OFFSET ' . $offset,
            $extensions,
        );

        $processed = 0;
        $lastFilename = null;
        foreach ($sql as $row) {
            $lastFilename = (string) $row->getValue('filename');
            \rex_media_manager::create($mmType, $lastFilename);
            ++$processed;
        }

        $nextOffset = $offset + $processed;

        \rex_response::sendJson([
            'processed' => $processed,
            'next_offset' => $nextOffset,
            'total' => $total,
            'done' => $processed < self::BATCH_SIZE || $nextOffset >= $total,
            // Fuer die Live-Vorschau im UI (thumb_warmup.php) -- nur die
            // zuletzt in diesem Batch angefasste Datei, kein Anspruch auf
            // "jedes Bild im Batch einzeln anzeigen".
            'last_filename' => $lastFilename,
            'last_thumb_url' => null !== $lastFilename ? \rex_media_manager::getUrl($mmType, $lastFilename, null, false) : null,
        ]);
    }

    /**
     * @param list<string> $extensions
     */
    private function countForExtensions(array $extensions): int
    {
        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($extensions), '?'));
        $row = $sql->getArray(
            'SELECT COUNT(*) AS c FROM ' . \rex::getTable('media') . '
             WHERE LOWER(SUBSTRING_INDEX(filename, \'.\', -1)) IN (' . $placeholders . ')',
            $extensions,
        );

        return (int) ($row[0]['c'] ?? 0);
    }

    /**
     * Alle Vorschaubild-URLs (Bild + ggf. Video/PDF), eine Liste, in der Reihenfolge
     * wie warm_batch sie auch einzeln abarbeiten wuerde (ID aufsteigend, Bilder
     * vor Videos vor PDFs).
     *
     * @return list<string>
     */
    private function buildAllUrls(): array
    {
        $urls = [];
        $urls = array_merge($urls, $this->urlsForType(ThumbWarmupCronjob::IMAGE_TYPE, ThumbWarmupCronjob::IMAGE_EXTENSIONS));

        $videoType = FfmpegIntegration::isAvailable() ? FfmpegIntegration::getActiveVideoThumbType() : null;
        if (null !== $videoType) {
            $urls = array_merge($urls, $this->urlsForType($videoType, FfmpegIntegration::supportedExtensions()));
        }

        $pdfType = PdfoutIntegration::getActiveThumbType();
        if (null !== $pdfType) {
            $urls = array_merge($urls, $this->urlsForType($pdfType, PdfoutIntegration::EXTENSIONS));
        }

        return $urls;
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function urlsForType(string $mmType, array $extensions): array
    {
        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($extensions), '?'));
        $sql->setQuery(
            'SELECT filename FROM ' . \rex::getTable('media') . '
             WHERE LOWER(SUBSTRING_INDEX(filename, \'.\', -1)) IN (' . $placeholders . ')
             ORDER BY id ASC',
            $extensions,
        );

        $urls = [];
        foreach ($sql as $row) {
            $urls[] = self::absoluteUrl(\rex_media_manager::getUrl($mmType, (string) $row->getValue('filename'), null, false));
        }

        return $urls;
    }

    /**
     * wget braucht absolute URLs: getUrl() liefert sie relativ zum Backend ("../media/...") bzw.
     * wurzel-relativ. Pfad gegen SCRIPT_NAME aufloesen, Host aus der server-Einstellung.
     */
    private static function absoluteUrl(string $url): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            return $url;
        }

        if (!str_starts_with($url, '/')) {
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
            $url = '/' . implode('/', $segments) . (null !== $query ? '?' . $query : '');
        }

        return self::serverOrigin() . $url;
    }

    /** "https://example.org" aus der server-Einstellung, sonst aus dem aktuellen Request */
    private static function serverOrigin(): string
    {
        $parts = parse_url(\rex::getServer());
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }

        return \rex_request::server('REQUEST_SCHEME', 'string', 'https') . '://' . \rex_request::server('HTTP_HOST', 'string', 'localhost');
    }

    /**
     * Startet den Hintergrundprozess: URL-Liste in eine eigene Datei
     * schreiben, dann per shell_exec() einen abgekoppelten xargs+wget-Prozess
     * anstossen -- gleiches Prinzip wie ffmpeg's Api\Converter::handleStart()
     * (shell_exec('(...) > logfile 2>&1 &')), nur mit wget statt ffmpeg als
     * eigentlichem Arbeitspferd. wget schreibt bei jedem erledigten Download
     * (Erfolg ODER Fehlschlag -- Semikolon statt "&&", ein einzelner defekter
     * Link soll den Rest nicht blockieren) die URL in PROGRESS_FILE, das ist
     * die einzige Fortschrittsquelle fuer handleBackgroundStatus().
     */
    private function handleStartBackground(): void
    {
        if (!self::shellExecAvailable()) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'shell_exec not available on this server']);
            return;
        }

        $urls = $this->buildAllUrls();
        if ([] === $urls) {
            \rex_response::sendJson(['started' => true, 'total' => 0]);
            return;
        }

        $urlsFile = self::stateFile('urls.txt');
        $progressFile = self::stateFile('progress.log');
        $bgLogFile = self::stateFile('background.log');
        $pidFile = self::stateFile('background.pid');

        \rex_file::put($urlsFile, implode("\n", $urls) . "\n");
        \rex_file::put($progressFile, '');

        // "$1" statt woertlichem "{}" im inneren sh -c: xargs -I{} ersetzt
        // {} sonst per Textsubstitution IN DER BEFEHLSZEILE, bevor die Shell
        // sie parst -- bei einer URL mit Sonderzeichen waere das ein
        // Quoting-/Injection-Risiko. Als $1 (echtes Argument des inneren
        // sh -c) uebernimmt die Shell selbst das sichere Escaping.
        // Selbstaufruf des eigenen Servers: Zertifikat nicht pruefen (selbstsigniert auf Staging/Dev).
        // Fehlschlaege landen als "FAIL" in der Fortschrittsdatei und zaehlen nicht als erzeugt.
        $innerScript = 'if wget -q --no-check-certificate -T 30 -t 1 -O /dev/null "$1"; then echo "OK $1"; else echo "FAIL $1"; fi >> ' . escapeshellarg($progressFile);
        $pipeline = 'xargs -P ' . self::BACKGROUND_CONCURRENCY . ' -a ' . escapeshellarg($urlsFile)
            . ' -I{} sh -c ' . escapeshellarg($innerScript) . ' _ {}';

        shell_exec('(' . $pipeline . ') > ' . escapeshellarg($bgLogFile) . ' 2>&1 & echo $! > ' . escapeshellarg($pidFile));

        \rex_response::sendJson(['started' => true, 'total' => count($urls)]);
    }

    /**
     * "Fertig" wird rein ueber den Zeilenabgleich Progress- vs. URL-Datei
     * erkannt (keine PID-Lebendigkeitspruefung noetig, prozess-/plattform-
     * unabhaengig) -- sobald jede URL einmal in progress.log auftaucht, ist
     * der Hintergrundlauf fertig, ganz gleich ob der Prozess selbst noch
     * "existiert".
     */
    private function handleBackgroundStatus(): void
    {
        $urlsFile = self::stateFile('urls.txt');
        $progressFile = self::stateFile('progress.log');

        if (!is_file($urlsFile)) {
            \rex_response::sendJson(['running' => false, 'processed' => 0, 'total' => 0, 'done' => true, 'last_thumb_url' => null]);
            return;
        }

        $total = self::countLines($urlsFile);
        $processedLines = is_file($progressFile) ? self::readLines($progressFile) : [];
        $processed = count($processedLines);
        $failed = 0;
        $lastUrl = null;
        foreach ($processedLines as $line) {
            if (str_starts_with($line, 'FAIL ')) {
                ++$failed;
                continue;
            }
            $lastUrl = str_starts_with($line, 'OK ') ? substr($line, 3) : $line;
        }

        \rex_response::sendJson([
            'running' => $processed < $total,
            'processed' => $processed,
            'failed' => $failed,
            'total' => $total,
            'done' => $processed >= $total,
            'last_thumb_url' => $lastUrl,
            'server' => self::serverOrigin(),
        ]);
    }

    /**
     * Best effort: beendet nur den gemerkten xargs-Prozess selbst -- bereits
     * von xargs gestartete, noch laufende wget-Kindprozesse (bis zu
     * BACKGROUND_CONCURRENCY Stueck) werden dadurch nicht hart abgebrochen,
     * laufen aber einzeln aus und starten danach keine neuen mehr (xargs
     * selbst ist tot). Kein Prozessgruppen-Kill, um keine fremden Prozesse
     * mit zufaellig kollidierender PID zu treffen.
     */
    private function handleStopBackground(): void
    {
        $pidFile = self::stateFile('background.pid');
        $pid = is_file($pidFile) ? (int) trim(\rex_file::get($pidFile, '')) : 0;
        if ($pid > 0 && self::shellExecAvailable()) {
            shell_exec('kill ' . $pid . ' 2>/dev/null');
        }
        // urls.txt auf progress-Stand kappen, damit handleBackgroundStatus()
        // den abgebrochenen Lauf als "fertig" (statt ewig "laeuft") zeigt.
        $urlsFile = self::stateFile('urls.txt');
        $progressFile = self::stateFile('progress.log');
        if (is_file($urlsFile) && is_file($progressFile)) {
            \rex_file::put($urlsFile, (string) preg_replace('/^(OK|FAIL) /m', '', \rex_file::get($progressFile, '')));
        }

        \rex_response::sendJson(['stopped' => true]);
    }

    private static function shellExecAvailable(): bool
    {
        return function_exists('shell_exec')
            && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
    }

    private static function stateFile(string $name): string
    {
        return \rex_path::addonData('mediaplace', 'thumb_warmup_' . $name);
    }

    private static function countLines(string $file): int
    {
        return count(self::readLines($file));
    }

    /**
     * @return list<string>
     */
    private static function readLines(string $file): array
    {
        $content = trim(\rex_file::get($file, ''));

        return '' === $content ? [] : explode("\n", $content);
    }
}
