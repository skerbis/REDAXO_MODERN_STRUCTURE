<?php

namespace FriendsOfRedaxo\Mediaplace\Widgets;

use FriendsOfRedaxo\Mediaplace\MetainfoWidget;

/**
 * ALT text field widget (for images only).
 * Includes checkbox to mark image as decorative (no alt needed).
 * Shows warning if no alt text provided for non-decorative images.
 */
class AltFieldWidget extends MetainfoWidget
{
    public function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            $value = ['text' => $value, 'decorative' => false];
        }

        // Normalize text values
        if (isset($value['text'])) {
            if (is_string($value['text'])) {
                $value['text'] = trim($value['text']);
            } elseif (is_array($value['text'])) {
                $value['text'] = array_filter(array_map(fn($v) => is_string($v) ? trim($v) : $v, $value['text']));
            }
            if (empty($value['text'])) {
                unset($value['text']);
            }
        }

        // Normalize decorative flag
        $value['decorative'] = (bool) ($value['decorative'] ?? false);

        // If empty, return null to remove field
        if (empty($value['text']) && !$value['decorative']) {
            return null;
        }

        return $value;
    }
}
