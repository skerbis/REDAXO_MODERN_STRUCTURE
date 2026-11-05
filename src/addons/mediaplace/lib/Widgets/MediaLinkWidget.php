<?php

namespace FriendsOfRedaxo\Mediaplace\Widgets;

use FriendsOfRedaxo\Mediaplace\MetainfoWidget;

/**
 * Media link widget – link to another media file.
 * Useful for poster images, thumbnails, etc.
 */
class MediaLinkWidget extends MetainfoWidget
{
    public function normalizeValue(mixed $value): mixed
    {
        $filename = '';

        if (is_string($value)) {
            $filename = trim($value);
        } elseif (is_array($value) && isset($value['filename'])) {
            $filename = trim($value['filename']);
        }

        if (!$filename) {
            return null;
        }

        // Validate that media file exists
        if (!\rex_media::get($filename)) {
            return null;
        }

        return $filename;
    }

    /**
     * Get linked media file.
     */
    public static function getLinkedMedia(\rex_media $media, string $fieldKey): ?\rex_media
    {
        $data = \FriendsOfRedaxo\Mediaplace\MetainfoJsonStorage::loadFromMedia($media);
        $filename = $data[$fieldKey] ?? null;

        if (!is_string($filename) || !$filename) {
            return null;
        }

        return \rex_media::get($filename);
    }
}
