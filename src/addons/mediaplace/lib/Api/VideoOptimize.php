<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * "Video optimieren"-Button im Detail-Panel -- duenner Wrapper um das
 * separate ffmpeg-Addon (FriendsOfRedaxo\FFmpeg\Api\Converter, siehe
 * FfmpegIntegration), der nur die MediaPlace-eigenen Rechte durchsetzt
 * (Kategorie-Zugriff + eigenes Rollenrecht mediaplace[optimize_video]) --
 * die eigentliche Job-Engine (Start/Polling/Fertigstellen) bleibt komplett
 * in ffmpeg, hier wird nichts dupliziert.
 *
 * GET ?rex-api-call=mediaplace_video_optimize&func=start&file=...
 *     Startet einen Job im 'overwrite'-Modus (Original wird ersetzt).
 * GET ?rex-api-call=mediaplace_video_optimize&func=status&job=...
 *     Pollt den Job-Status (siehe Converter::pollJob()).
 */
class VideoOptimize extends rex_api_function
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

        if (!\FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isAvailable()) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'ffmpeg addon not available']);
            exit;
        }

        if (!$user->hasPerm('mediaplace[optimize_video]')) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        if ('start' === rex_request('func', 'string', '')) {
            $this->handleStart($user);
        } else {
            $this->handleStatus();
        }
        exit;
    }

    private function handleStart(\rex_user $user): void
    {
        $filename = rex_request('file', 'string', '');
        $media = '' !== $filename ? \rex_media::get($filename) : null;
        if (!$media) {
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            \rex_response::sendJson(['error' => 'Media not found']);
            return;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            return;
        }

        if (!\FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isSupportedVideo($filename)) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Unsupported video type']);
            return;
        }

        try {
            $result = \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::startOptimizeJob($filename, $user);
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            return;
        }

        \rex_response::sendJson($result);
    }

    /**
     * Kein Kategorie-Rechte-Check hier -- die Job-ID ist ein 24-stelliger
     * Zufallswert (siehe VideoJob::create() im ffmpeg-Addon), nicht erratbar,
     * und der Start-Aufruf hat die Berechtigung bereits durchgesetzt. Reine
     * Statusabfrage, keine Aktion.
     */
    private function handleStatus(): void
    {
        $jobId = rex_request('job', 'string', '');
        if ('' === $jobId) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Missing job id']);
            return;
        }

        try {
            $result = \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::pollOptimizeJob($jobId);
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            return;
        }

        \rex_response::sendJson($result);
    }
}
