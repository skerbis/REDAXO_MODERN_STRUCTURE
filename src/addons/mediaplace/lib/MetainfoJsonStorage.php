<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Handles reading and writing metadata JSON from/to media files.
 * Supports translatable fields (stores language codes as keys).
 *
 * JSON structure:
 * {
 *   "alt": { "text": { "1": "Alt text EN", "2": "Alt text DE" }, "decorative": false },
 *   "description": { "1": "...", "2": "..." },
 *   ...
 * }
 */
class MetainfoJsonStorage
{
    /**
     * Load metadata JSON from a media file.
     */
    public static function loadFromMedia(\rex_media $media): array
    {
        // Read directly from DB to avoid stale values from rex_media cache.
        return self::loadFromFilename($media->getFileName());
    }

    /**
     * Load metadata JSON from filename.
    * @return array<string, mixed>
     */
    public static function loadFromFilename(string $filename): array
    {
        $sql = \rex_sql::factory();
        $row = $sql->getArray(
            'SELECT med_json_data FROM ' . \rex::getTable('media') . ' WHERE filename = ?',
            [$filename],
        );

        if (empty($row)) {
            return [];
        }

        $jsonData = $row[0]['med_json_data'] ?? null;
        if (!$jsonData) {
            return [];
        }

        $decoded = json_decode($jsonData, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Save metadata JSON to a media file.
     */
    public static function saveToMedia(\rex_media $media, array $data): bool
    {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('media'));
        $sql->setWhere('id = :id', ['id' => $media->getId()]);
        $sql->setValue('med_json_data', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $sql->update();

        if (class_exists('rex_media_cache')) {
            \rex_media_cache::delete($media->getFileName());
        }

        return true;
    }

    /**
     * Save metadata JSON by filename.
     */
    public static function saveToFilename(string $filename, array $data): bool
    {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('media'));
        $sql->setWhere('filename = :filename', ['filename' => $filename]);
        $sql->setValue('med_json_data', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $sql->update();

        if (class_exists('rex_media_cache')) {
            \rex_media_cache::delete($filename);
        }

        return true;
    }

    /**
     * Get a field value from metadata.
     * For translatable fields, returns language-keyed array: { "1": "...", "2": "..." }
     * For non-translatable, returns scalar or nested array.
     */
    public static function getFieldValue(array $data, string $fieldKey): mixed
    {
        return $data[$fieldKey] ?? null;
    }

    /**
     * Set a field value in metadata.
     * For translatable fields, $value should be: { "1": "...", "2": "..." }
     */
    public static function setFieldValue(array &$data, string $fieldKey, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            unset($data[$fieldKey]);
        } else {
            $data[$fieldKey] = $value;
        }
    }

    /**
     * Get value for a specific language from a translatable field.
     */
    public static function getTranslatedValue(array $data, string $fieldKey, int $clangId): mixed
    {
        $fieldData = $data[$fieldKey] ?? null;
        if (!is_array($fieldData)) {
            return null;
        }
        return $fieldData[(string) $clangId] ?? null;
    }

    /**
     * Set value for a specific language in a translatable field.
     */
    public static function setTranslatedValue(array &$data, string $fieldKey, int $clangId, mixed $value): void
    {
        if (!isset($data[$fieldKey])) {
            $data[$fieldKey] = [];
        }

        if (!is_array($data[$fieldKey])) {
            $data[$fieldKey] = [];
        }

        $clangKey = (string) $clangId;
        if ($value === null || $value === '') {
            unset($data[$fieldKey][$clangKey]);
            // If no values left, remove the field
            if (empty($data[$fieldKey])) {
                unset($data[$fieldKey]);
            }
        } else {
            $data[$fieldKey][$clangKey] = $value;
        }
    }

    /**
     * Remove a field from metadata.
     */
    public static function removeField(array &$data, string $fieldKey): void
    {
        unset($data[$fieldKey]);
    }
}
