<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * MediaPlace – Fokuspunkt-Integration mit dem separaten focuspoint-Addon.
 *
 * Zwei Actions:
 *   GET  ?rex-api-call=mediaplace_focuspoint&action=info&file=...
 *        Liefert die fokuspunkt-relevanten Media-Manager-Typen, alle
 *        Fokuspunkt-Metafelder und deren aktuell gespeicherte Werte fuer
 *        die angegebene Datei.
 *   POST ?rex-api-call=mediaplace_focuspoint&action=save
 *        Body: file, meta, xy. Speichert einen neuen Fokuspunkt-Wert.
 *
 * Beide Actions pruefen zusaetzlich zur normalen Medien-Berechtigung
 * (MediaPermission::hasCategoryAccess()) serverseitig, ob das focuspoint-
 * Addon ueberhaupt verfuegbar ist -- der Client sollte den Endpunkt ohne
 * data-focuspoint-available="1" nie aufrufen, das ist aber kein Ersatz fuer
 * eine eigene serverseitige Absicherung.
 */
class Focuspoint extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\FocuspointIntegration::isAvailable()) {
            \rex_response::setStatus('501 Not Implemented');
            \rex_response::sendJson(['error' => 'focuspoint addon not available']);
            exit;
        }

        $action = rex_request('action', 'string', 'info');
        $filename = rex_request('file', 'string', '');

        $media = '' !== $filename ? \rex_media::get($filename) : null;
        if (!$media) {
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            \rex_response::sendJson(['error' => 'Media not found']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        if (!$media->isImage()) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Not an image']);
            exit;
        }

        if ('save' === $action) {
            $this->handleSave($filename);
        } else {
            $this->handleInfo($filename);
        }
        exit;
    }

    private function handleInfo(string $filename): void
    {
        $fields = \FriendsOfRedaxo\Mediaplace\FocuspointIntegration::getMetafields();

        $current = [];
        foreach ($fields as $field) {
            $current[$field] = \FriendsOfRedaxo\Mediaplace\FocuspointIntegration::getFocus($filename, $field);
        }

        \rex_response::sendJson([
            'success' => true,
            'types' => \FriendsOfRedaxo\Mediaplace\FocuspointIntegration::getTypesForImage(),
            'fields' => $fields,
            'current' => $current,
        ]);
    }

    private function handleSave(string $filename): void
    {
        if ('post' !== rex_request_method()) {
            \rex_response::setStatus('405 Method Not Allowed');
            \rex_response::sendJson(['error' => 'POST required']);
            return;
        }

        $metafield = rex_request('meta', 'string', '');
        $xy = rex_request('xy', 'string', '');

        if (!\FriendsOfRedaxo\Mediaplace\FocuspointIntegration::saveFocus($filename, $metafield, $xy)) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Invalid field or coordinate']);
            return;
        }

        \rex_response::sendJson(['success' => true]);
    }
}
