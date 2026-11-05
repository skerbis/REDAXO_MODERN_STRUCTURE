<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use FriendsOfRedaxo\Mediaplace\ImageEditor;
use rex_api_function;
use rex_api_result;
use rex_response;

/**
 * Bildbearbeitung (siehe ImageEditor).
 *
 * GET  action=info&file=…    Fähigkeiten, Rechte, ob ein Original aufbewahrt ist
 * POST action=save           file, ops (JSON), mode (overwrite|copy), name
 * POST action=restore        file
 */
class ImageEdit extends rex_api_function
{
    public const CSRF = 'mediaplace_image_edit';

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $filename = rex_request('file', 'string', '');
        $media = '' !== $filename ? \rex_media::get($filename) : null;
        if (null === $media) {
            $this->send(['error' => 'Media not found'], rex_response::HTTP_NOT_FOUND);
        }
        if (!ImageEditor::canEdit($media)) {
            $this->send(['error' => \rex_i18n::rawMsg('mediaplace_media_permission_denied')], rex_response::HTTP_FORBIDDEN);
        }

        $action = rex_request('action', 'string', 'info');
        if ('info' === $action) {
            $this->send($this->info($media));
        }

        if ('post' !== rex_request_method() || !\rex_csrf_token::factory(self::CSRF)->isValid()) {
            $this->send(['error' => \rex_i18n::rawMsg('csrf_token_invalid')], rex_response::HTTP_FORBIDDEN);
        }

        try {
            if ('restore' === $action) {
                if (!ImageEditor::canOverwrite()) {
                    $this->send(['error' => \rex_i18n::rawMsg('mediaplace_media_permission_denied')], rex_response::HTTP_FORBIDDEN);
                }
                ImageEditor::restore($media);
                $this->send(['success' => true, 'filename' => $media->getFileName()]);
            }

            if ('save' === $action) {
                $asCopy = 'copy' === rex_post('mode', 'string', 'copy');
                if ($asCopy ? !ImageEditor::canSaveCopy() : !ImageEditor::canOverwrite()) {
                    $this->send(['error' => \rex_i18n::rawMsg('mediaplace_media_permission_denied')], rex_response::HTTP_FORBIDDEN);
                }
                $ops = json_decode(rex_post('ops', 'string', '{}'), true);
                $result = ImageEditor::save($media, is_array($ops) ? $ops : [], $asCopy, rex_post('name', 'string', ''));
                $this->send(['success' => true, 'filename' => $result]);
            }
        } catch (\Throwable $e) {
            \rex_logger::logException($e);
            $this->send(['error' => $e->getMessage()], rex_response::HTTP_INTERNAL_ERROR);
        }

        $this->send(['error' => 'Unknown action'], rex_response::HTTP_BAD_REQUEST);
    }

    /**
     * @return array<string, mixed>
     */
    private function info(\rex_media $media): array
    {
        $filename = $media->getFileName();

        return [
            'success' => true,
            'capabilities' => ImageEditor::capabilities(),
            'canOverwrite' => ImageEditor::canOverwrite(),
            'canCopy' => ImageEditor::canSaveCopy(),
            'hasBackup' => ImageEditor::hasBackup($filename),
            'copyName' => pathinfo($filename, PATHINFO_FILENAME) . '_edit',
            'extension' => strtolower(\rex_file::extension($filename)),
            'src' => \rex_url::media($filename) . '?v=' . (@filemtime(\rex_path::media($filename)) ?: time()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return never
     */
    private function send(array $data, string $status = rex_response::HTTP_OK): void
    {
        rex_response::setStatus($status);
        rex_response::sendJson($data);
        exit;
    }
}
