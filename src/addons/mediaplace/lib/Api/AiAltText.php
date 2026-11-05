<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use FriendsOfRedaxo\Mediaplace\AiAltTextService;
use FriendsOfRedaxo\Mediaplace\AiAltTextWriter;
use FriendsOfRedaxo\Mediaplace\MediaPermission;

/**
 * Einzeldatei-ALT-Text-Generierung fuer den "AI generieren"-Button im
 * Detail-Panel bzw. nativen Metainfo-Canvas (siehe modules/ai_alt.js).
 * Schreibt BEWUSST NICHT selbst in die Datenbank -- der generierte Text
 * wird nur zurueckgegeben, das Client-JS traegt ihn ins sichtbare Feld ein
 * und ueberlaesst das eigentliche Speichern dem normalen Speichern-Button
 * (Review-vor-Speichern-Prinzip). Api\AiAltBulk.php folgt demselben Prinzip
 * ueber eine eigene Pruefliste, siehe dortiger Docblock.
 */
class AiAltText extends \rex_api_function
{
    protected $published = true;

    public function execute(): \rex_api_result
    {
        if (!\rex::getUser()) {
            $this->send(['success' => false, 'error' => 'Nicht angemeldet.'], 401);
        }
        if (!AiAltTextService::isAvailable()) {
            $this->send(['success' => false, 'error' => 'KI-Alt-Text-Generierung ist nicht aktiviert oder ai_platform nicht verfügbar.'], 403);
        }
        if (!MediaPermission::hasMediaAccess()) {
            $this->send(['success' => false, 'error' => 'Keine Berechtigung.'], 403);
        }

        // Body kommt als application/json (siehe apiGenerateAiAltText() in
        // mediaplace-api.js) -- rex_request() liest nur $_GET/$_POST, PHP
        // befuellt $_POST aber nicht bei einem JSON-Content-Type, daher
        // manuell aus dem Body dekodieren (gleiches Muster wie Api\AiAltBulk.php).
        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        $filename = (string) ($body['filename'] ?? \rex_request('filename', 'string', ''));
        // Client-seitig auf Canvas gerendertes PNG fuer SVGs (kein
        // serverseitiger Rasterizer verfuegbar) -- siehe
        // AiAltTextService::generateAltText()-Docblock und
        // rasterizeSvgToPngDataUrl() in modules/ai_alt.js.
        $imageData = isset($body['imageData']) && is_string($body['imageData']) && '' !== $body['imageData']
            ? $body['imageData']
            : null;
        $media = '' !== $filename ? \rex_media::get($filename) : null;
        if (!$media) {
            $this->send(['success' => false, 'error' => 'Datei nicht gefunden.'], 404);
        }
        if (!MediaPermission::hasCategoryAccess($media->getCategoryId())) {
            $this->send(['success' => false, 'error' => 'Kein Zugriff auf diese Kategorie.'], 403);
        }
        if (!$media->isImage()) {
            $this->send(['success' => false, 'error' => 'Keine Bilddatei.'], 400);
        }

        $clangIds = AiAltTextWriter::resolveClangIds();

        try {
            $service = new AiAltTextService();
            $texts = $service->generateAltText($media, $clangIds, $imageData);
        } catch (\Throwable $e) {
            $this->send(['success' => false, 'error' => $e->getMessage()], 500);
        }

        if ([] === $texts) {
            $this->send(['success' => false, 'error' => 'Keine verwertbare Antwort vom KI-Dienst erhalten.'], 500);
        }

        $this->send(['success' => true, 'texts' => $texts]);
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
