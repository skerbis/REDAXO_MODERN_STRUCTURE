<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use FriendsOfRedaxo\Mediaplace\AiAutoTagService;
use FriendsOfRedaxo\Mediaplace\MediaPermission;

/**
 * Einzeldatei-Tag-Vorschlaege fuer den "AI Tags vorschlagen"-Button im
 * Tag-Widget des Detail-Panels (siehe modules/ai_tags.js). Schreibt BEWUSST
 * NICHT selbst -- die vorgeschlagenen Tags werden nur zurueckgegeben, das
 * Client-JS zeigt sie als anklickbare Chips, tatsaechlich hinzugefuegt (und
 * damit Teil des normalen Speichern-Vorgangs) wird ein Tag erst per Klick
 * (gleiche addTagFromWidget()-Funktion wie bei manueller Eingabe). Gleiches
 * Review-vor-Speichern-Prinzip wie Api\AiAltText.php.
 */
class AiAutoTag extends \rex_api_function
{
    protected $published = true;

    public function execute(): \rex_api_result
    {
        if (!\rex::getUser()) {
            $this->send(['success' => false, 'error' => 'Nicht angemeldet.'], 401);
        }
        if (!AiAutoTagService::isAvailable()) {
            $this->send(['success' => false, 'error' => 'KI-Tag-Vorschläge sind nicht aktiviert, ai_platform nicht verfügbar oder keine Tags für KI freigegeben.'], 403);
        }
        if (!MediaPermission::hasMediaAccess()) {
            $this->send(['success' => false, 'error' => 'Keine Berechtigung.'], 403);
        }

        // Body kommt als application/json, siehe apiSuggestAiTags() in
        // mediaplace-api.js -- gleiches Muster wie Api\AiAltText.php.
        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        $filename = (string) ($body['filename'] ?? \rex_request('filename', 'string', ''));
        // Client-seitig auf Canvas gerendertes PNG fuer SVGs, siehe
        // AiImagePreparer-Docblock und rasterizeSvgToPngDataUrl() in
        // modules/ai_alt.js (von ai_tags.js mitgenutzt).
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

        try {
            $service = new AiAutoTagService();
            $tags = $service->suggestTags($media, $imageData);
        } catch (\Throwable $e) {
            $this->send(['success' => false, 'error' => $e->getMessage()], 500);
        }

        $this->send(['success' => true, 'tags' => $tags]);
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
