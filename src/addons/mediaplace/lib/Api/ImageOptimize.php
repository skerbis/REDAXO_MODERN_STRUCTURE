<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * "Bild optimieren"-Button im Detail-Panel -- resized ein bereits
 * gespeichertes, zu grosses Bild in-place auf die konfigurierte
 * Upload-Resize-Groesse (siehe ImageOptimizer). Anders als beim
 * Video-Optimieren (ffmpeg, asynchroner Job) reicht hier ein einzelner
 * synchroner Request, da GD-Resize auch bei grossen Bildern schnell ist --
 * kein Start/Status-Split noetig.
 *
 * GET ?rex-api-call=mediaplace_image_optimize&func=optimize&file=...
 */
class ImageOptimize extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        $user = \rex::getUser();
        if (!$user) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\ImageOptimizer::isEnabled()) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Image optimizing not enabled']);
            exit;
        }

        if (!$user->hasPerm('mediaplace[optimize_image]')) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

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

        if (!\FriendsOfRedaxo\Mediaplace\ImageOptimizer::isSupportedType($filename)) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Unsupported image type']);
            exit;
        }

        try {
            $result = \FriendsOfRedaxo\Mediaplace\ImageOptimizer::optimize($filename);
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }

        \rex_response::sendJson($result);
        exit;
    }
}
