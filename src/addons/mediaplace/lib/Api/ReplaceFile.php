<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_exception;
use rex_api_function;
use rex_api_result;

/**
 * Ersetzt den Dateiinhalt einer bestehenden Mediendatei (Detail-Panel,
 * "Ersetzen" -> vom Geraet). Eigener, schlanker Endpunkt statt der
 * api-Addon-Route media/{filename}/update: die akzeptiert dort ausschliesslich
 * PUT/PATCH -- und genau das ist auf PHP-Installationen mit aktivem (Default)
 * enable_post_data_reading ein echtes Problem: PHP liest den kompletten
 * multipart/form-data-Body bereits VOR jedem Nutzcode aus php://input, um ihn
 * (nur bei POST) in $_FILES zu befuellen. Bei PATCH/PUT wird der Body dabei
 * TROTZDEM restlos konsumiert, aber verworfen, ohne $_FILES zu befuellen --
 * fuer einen manuellen Nachparse-Versuch bleibt dann buchstaeblich nichts mehr
 * uebrig (verifiziert per Debug-Log + echtem Browser-Request: der PATCH-Call
 * meldete Erfolg, die Datei blieb aber unveraendert, kein Fehler sichtbar).
 *
 * Das api-Addon selbst darf/soll hier nicht angepasst werden (separates,
 * fremdes Repo) -- deshalb dieser eigene Endpunkt: ein echtes POST an eine
 * MediaPlace-eigene Route, PHP befuellt $_FILES damit nativ und zuverlaessig,
 * kein eigener Multipart-Parser noetig. Ruft dieselbe REDAXO-Core-Methode
 * (rex_media_service::updateMedia()) wie der klassische Medienpool selbst
 * auf -- Cache-Invalidierung (rex_media_cache::delete()) und MEDIA_UPDATED-EP
 * laufen darueber unveraendert mit.
 */
class ReplaceFile extends rex_api_function
{
    // Siehe Api\StorageUsage-Kommentar: umgeht nur das isBackend()-Gate,
    // execute() prueft Login+Rechte ohnehin selbst.
    protected $published = true;

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        $user = \rex::getUser();
        if (!$user) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        $filename = rex_request('filename', 'string', '');
        $media = '' !== $filename ? \rex_media::get($filename) : null;
        if (!$media) {
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            \rex_response::sendJson(['error' => 'Media not found']);
            exit;
        }

        // permitted_only=1: kaskadierende Rechtepruefung (Zugriff auf eine
        // Kategorie gilt auch fuer ihren Unterbaum), gleiche Konvention wie
        // apiUpdate()/apiDelete() ueber die api-Addon-Route bisher.
        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || empty($file['name'])) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'No file uploaded']);
            exit;
        }

        try {
            $result = \rex_media_service::updateMedia($filename, [
                'title' => $media->getTitle(),
                'category_id' => $media->getCategoryId(),
                'file' => $file,
            ]);
        } catch (rex_api_exception $e) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }

        if (!($result['ok'] ?? false)) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => $result['msg'] ?? 'Unknown error']);
            exit;
        }

        \rex_response::sendJson(['message' => 'Media updated', 'filename' => $filename]);
        exit;
    }
}
