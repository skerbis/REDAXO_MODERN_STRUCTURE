<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Editiert echte Metainfo-Felder (med_*) im MediaPlace-Overlay ueber
 * REDAXOs eigenen MEDIA_FORM_EDIT/MEDIA_UPDATED-Pfad statt eigener
 * Feldtyp-Widgets.
 *
 * GET  ?rex-api-call=mediaplace_metainfo_form&file=...
 *      Liefert das von MEDIA_FORM_EDIT gerenderte Formular-HTML.
 *
 * POST ?rex-api-call=mediaplace_metainfo_form&file=...
 *      Body: Feldwerte als multipart/form-data (kein JSON -- REDAXOs
 *      Speicherpfad liest $_POST direkt). Speichert ueber
 *      rex_media_service::updateMedia(), das MEDIA_UPDATED feuert.
 */
class MetainfoForm extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\rex_addon::get('metainfo')->isAvailable()) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'metainfo addon not available']);
            exit;
        }

        if (!\rex_config::get('mediaplace', 'enable_metainfo_editing', false)) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'metainfo editing is disabled']);
            exit;
        }

        $this->ensureMetainfoMediaHandler();

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

        if ('post' === rex_request_method()) {
            $this->handleSave($media);
        } else {
            $this->handleForm($media);
        }
        exit;
    }

    private function handleForm(\rex_media $media): void
    {
        // MEDIA_FORM_EDIT erwartet ein rex_sql-Objekt mit der vollen Zeile.
        $gf = \rex_sql::factory();
        $gf->setQuery('SELECT * FROM ' . \rex::getTablePrefix() . 'media WHERE id = ?', [$media->getId()]);
        if (1 !== $gf->getRows()) {
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            \rex_response::sendJson(['error' => 'Media not found']);
            return;
        }

        $html = \rex_extension::registerPoint(new \rex_extension_point('MEDIA_FORM_EDIT', '', [
            'id' => $media->getId(),
            'media' => $gf,
        ]));

        \rex_response::sendJson(['success' => true, 'html' => $this->stripCropperField((string) $html)]);
    }

    /**
     * Entfernt den Link des cropper-Addons auf seine klassische Seite; im Overlay
     * würde er MediaPlace verlassen (dort gibt es die eigene Bildbearbeitung).
     */
    private function stripCropperField(string $html): string
    {
        if (!str_contains($html, 'page=mediapool/cropper')) {
            return $html;
        }

        $doc = new \DOMDocument();
        $prevErrorHandling = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($prevErrorHandling);

        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//a[contains(@href, "page=mediapool/cropper")]') as $link) {
            $removeNode = $link;
            $ancestor = $link->parentNode;
            while ($ancestor instanceof \DOMElement) {
                if ('dl' === strtolower($ancestor->nodeName)) {
                    $removeNode = $ancestor;
                    break;
                }
                $ancestor = $ancestor->parentNode;
            }
            $removeNode->parentNode?->removeChild($removeNode);
        }

        $wrapper = $doc->getElementsByTagName('div')->item(0);
        if (!$wrapper) {
            return $html;
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= (string) $doc->saveHTML($child);
        }

        return $result;
    }

    private function handleSave(\rex_media $media): void
    {
        try {
            \rex_media_service::updateMedia($media->getFileName(), [
                'title' => $media->getTitle(),
                'category_id' => $media->getCategoryId(),
            ]);
        } catch (\rex_api_exception $e) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            return;
        }

        \rex_response::sendJson(['success' => true]);
    }

    /**
     * media_handler.php registriert MEDIA_FORM_EDIT/MEDIA_UPDATED nur beim
     * Aufruf der klassischen Medienpool-Seite -- hier manuell nachladen.
     * @internal-Datei in metainfo, kein oeffentlicher Vertrag.
     */
    private function ensureMetainfoMediaHandler(): void
    {
        if (class_exists('rex_metainfo_media_handler', false)) {
            return;
        }
        $handlerPath = \rex_addon::get('metainfo')->getPath('lib/handler/');
        require_once $handlerPath . 'handler.php';
        require_once $handlerPath . 'media_handler.php';
    }
}
