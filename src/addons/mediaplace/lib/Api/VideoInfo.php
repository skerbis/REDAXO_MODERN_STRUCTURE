<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Lazy nachgeladene Video-Technikdaten (Aufloesung/Dauer/Codec/Bitrate/...)
 * fuer den aufklappbaren "Technische Details"-Bereich im Detail-Panel --
 * nutzt ffmpeg's bereits vorhandene FriendsOfRedaxo\FFmpeg\VideoInfo-Klasse
 * (ffprobe-Aufruf), siehe FfmpegIntegration::getVideoDetails(). Bewusst ein
 * eigener, separat abgerufener Endpunkt statt Teil von
 * Api\JsonMetainfo.php: ffprobe kostet spuerbar Zeit, das
 * normale Oeffnen des Detail-Panels soll nicht auf jeden Video-Aufruf warten.
 *
 * GET ?rex-api-call=mediaplace_video_info&file=...
 */
class VideoInfo extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isAvailable()) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'ffmpeg addon not available']);
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

        $details = \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::getVideoDetails($filename);
        if (null === $details) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'No video info available']);
            exit;
        }

        \rex_response::sendJson(['success' => true, 'details' => $details]);
        exit;
    }
}
