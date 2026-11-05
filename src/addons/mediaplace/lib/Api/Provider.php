<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Dispatcher fuer Cloud-Provider-Addons (siehe StorageProviderInterface /
 * StorageProviderRegistry) -- ein Endpunkt fuer Browsen/Suchen, Thumbnails
 * und Import, statt dass jeder Provider seine eigene, einzeln abzusichernde
 * Route mitbringen muss. Rechte-Pruefung zentral an einer Stelle:
 * StorageProviderRegistry::getInstance() liefert nur eine Instanz, wenn der
 * aktuelle User das vom Provider selbst deklarierte `perm` hat.
 *
 * GET  ?rex-api-call=mediaplace_provider&func=entries&provider=X&path=P[&search=Q]
 * GET  ?rex-api-call=mediaplace_provider&func=thumbnail&provider=X&path=P
 * GET  ?rex-api-call=mediaplace_provider&func=import&provider=X&path=P&category_id=C
 * POST ?rex-api-call=mediaplace_provider&func=import_batch&provider=X, JSON-Body {paths:[...], category_id:C}
 * GET  ?rex-api-call=mediaplace_provider&func=replace&provider=X&path=P&filename=F
 */
class Provider extends rex_api_function
{
    // Jeder Eintrag ist ein echter Netzwerk-Roundtrip zur entfernten Quelle
    // (Download + rex_media_service::addMedia()) -- deutlich teurer als eine
    // lokale Bulk-Operation, deshalb ein kleines Limit wie bei
    // Api\AiAltBulk.php, nicht die 100/500 von Api\CategoryBulk.php.
    private const IMPORT_BATCH_MAX = 25;

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        $user = \rex::getUser();
        if (!$user) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        $providerId = rex_request('provider', 'string', '');
        $provider = '' !== $providerId ? \FriendsOfRedaxo\Mediaplace\StorageProviderRegistry::getInstance($providerId) : null;
        if (!$provider) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Unknown provider or permission denied']);
            exit;
        }

        $func = rex_request('func', 'string', '');
        match ($func) {
            'entries' => $this->handleEntries($provider),
            'thumbnail' => $this->handleThumbnail($provider),
            'import' => $this->handleImport($provider),
            'import_batch' => $this->handleImportBatch($provider),
            'replace' => $this->handleReplace($provider),
            default => $this->handleUnknownFunc(),
        };
        exit;
    }

    private function handleEntries(\FriendsOfRedaxo\Mediaplace\StorageProviderInterface $provider): void
    {
        $path = rex_request('path', 'string', '');
        $search = rex_request('search', 'string', '');

        try {
            $entries = $provider->listEntries($path, '' !== $search ? $search : null);
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            return;
        }

        \rex_response::sendJson(['data' => $entries, 'has_search' => $provider->hasSearch()]);
    }

    private function handleThumbnail(\FriendsOfRedaxo\Mediaplace\StorageProviderInterface $provider): void
    {
        $path = rex_request('path', 'string', '');

        try {
            $thumbnail = $provider->getThumbnail($path);
        } catch (\Throwable $e) {
            $thumbnail = null;
        }

        if (!$thumbnail) {
            // Kein JSON-Fehler hier: der Client bindet diesen Endpunkt direkt
            // als <img src> ein und faellt bei einem fehlgeschlagenen Laden
            // auf das Datei-Icon zurueck (gleicher error-Listener wie bei
            // lokalen Video-/Bild-Vorschaubildern, siehe mediaplace.js).
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            exit;
        }

        // "private": Thumbnails koennen je nach Provider zugriffsgeschuetzt
        // sein, kein geteilter/oeffentlicher Proxy-Cache.
        \rex_response::setHeader('Cache-Control', 'private, max-age=3600');
        // sendContent() statt setHeader()+echo: setHeader() sammelt nur in
        // $additionalHeaders, das wird ausschliesslich von sendRedirect()/
        // sendFile()/sendContent() tatsaechlich per header() rausgeschrieben
        // (siehe rex_response::sendAdditionalHeaders(), private, nur von
        // diesen drei Stellen aufgerufen) -- ein rohes echo+exit wie zuvor
        // ignoriert alle per setHeader() gesetzten Header komplett, der
        // Browser bekommt PHPs Default-Content-Type (Bild kommt als Text an).
        \rex_response::sendContent($thumbnail['content'], $thumbnail['contentType']);
    }

    private function handleImport(\FriendsOfRedaxo\Mediaplace\StorageProviderInterface $provider): void
    {
        $path = rex_request('path', 'string', '');
        $categoryId = rex_request('category_id', 'int', 0);

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($categoryId)) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            return;
        }

        try {
            $filename = $provider->importToMediaPool($path, $categoryId);
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            return;
        }

        \rex_response::sendJson(['filename' => $filename]);
    }

    /**
     * Mehrere Pfade in einem Rutsch importieren ("Ausgewaehlte importieren"/
     * "Alle im Ordner importieren" im Client, siehe providers.js). Ruft
     * lediglich importToMediaPool() je Pfad in einer Schleife auf -- KEINE
     * Erweiterung von StorageProviderInterface noetig, jeder bestehende
     * Provider (z.B. nextcloud) funktioniert dadurch unveraendert weiter.
     * Schlaegt ein einzelner Import fehl, laufen die uebrigen trotzdem
     * durch (Ergebnis pro Pfad einzeln, kein Abbruch der ganzen Anfrage) --
     * der Client zeigt Erfolge/Fehler getrennt an.
     */
    private function handleImportBatch(\FriendsOfRedaxo\Mediaplace\StorageProviderInterface $provider): void
    {
        $categoryId = rex_request('category_id', 'int', 0);

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($categoryId)) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            return;
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        $paths = is_array($body['paths'] ?? null) ? $body['paths'] : [];
        $paths = array_values(array_filter(array_map(
            static fn (mixed $p): string => trim((string) $p),
            $paths,
        ), static fn (string $p): bool => '' !== $p));
        $paths = array_slice($paths, 0, self::IMPORT_BATCH_MAX);

        $results = [];
        foreach ($paths as $path) {
            try {
                $filename = $provider->importToMediaPool($path, $categoryId);
                $results[] = ['path' => $path, 'success' => true, 'filename' => $filename];
            } catch (\Throwable $e) {
                $results[] = ['path' => $path, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        \rex_response::sendJson(['results' => $results]);
    }

    /**
     * Ersetzt den Inhalt einer BESTEHENDEN lokalen Datei durch eine Cloud-
     * Datei (Gegenstueck zum lokalen "Ersetzen"-Dateidialog, siehe
     * apiReplaceFile() in mediaplace-api.js) -- anders als handleImport()
     * wird KEINE neue rex_media-Zeile angelegt, Dateiname/Kategorie/ID
     * bleiben unveraendert, nur Inhalt/Dateigroesse/Filetype werden
     * aktualisiert. Nur fuer Provider verfuegbar, die
     * StorageProviderContentInterface implementieren (rohe Datei-Bytes,
     * nicht nur Thumbnail/Import-als-neue-Datei) -- z.B. das aeltere,
     * eigenstaendige nextcloud-Addon bietet das (noch) nicht an, wird
     * deshalb hier sauber mit einer Fehlermeldung abgelehnt statt eines
     * TypeErrors.
     */
    private function handleReplace(\FriendsOfRedaxo\Mediaplace\StorageProviderInterface $provider): void
    {
        if (!$provider instanceof \FriendsOfRedaxo\Mediaplace\StorageProviderContentInterface) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'This provider does not support replacing files.']);
            return;
        }

        $path = rex_request('path', 'string', '');
        $filename = rex_request('filename', 'string', '');

        $media = '' !== $filename ? \rex_media::get($filename) : null;
        if (!$media) {
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            \rex_response::sendJson(['error' => 'Media not found']);
            return;
        }

        // Aktuelle (nicht eine Ziel-)Kategorie der zu ersetzenden Datei
        // pruefen -- anders als handleImport()/handleImportBatch(), die die
        // Ziel-Kategorie einer NEUEN Datei pruefen.
        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            return;
        }

        $tmpFile = \rex_path::cache('mediaplace_replace_' . \rex_string::normalize($filename));

        try {
            $content = $provider->getContent($path);
            \rex_file::put($tmpFile, $content);

            // WICHTIG: 'tmp_name', NICHT 'path' -- rex_media_service::updateMedia()
            // ueberschreibt $file['path'] unconditional mit $file['tmp_name']
            // (anders als addMedia(), das ein gesetztes 'path' respektiert).
            $result = \rex_media_service::updateMedia($filename, [
                'title' => $media->getTitle(),
                'category_id' => $media->getCategoryId(),
                'file' => [
                    'name' => basename($path),
                    'tmp_name' => $tmpFile,
                ],
            ]);
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            return;
        } finally {
            \rex_file::delete($tmpFile);
        }

        \rex_response::sendJson(['filename' => (string) $result['filename']]);
    }

    private function handleUnknownFunc(): void
    {
        \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
        \rex_response::sendJson(['error' => 'Unknown func']);
    }
}
