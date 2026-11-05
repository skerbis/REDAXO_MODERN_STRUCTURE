<?php

namespace FriendsOfRedaxo\Mediaplace\Widgets;

use FriendsOfRedaxo\Mediaplace\MetainfoWidget;

/**
 * Simple boolean toggle widget ("Ja/Nein"). Not translatable -- a bool
 * isn't language-dependent, enforced in pages/metainfo_fields.php's save
 * handler.
 */
class CheckboxWidget extends MetainfoWidget
{
    public function normalizeValue(mixed $value): mixed
    {
        return (bool) $value;
    }
}
