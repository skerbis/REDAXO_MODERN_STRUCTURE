<?php

namespace FriendsOfRedaxo\Mediaplace;

use rex_cronjob;
use rex_i18n;

/**
 * Setzt offene Umbenennungen fort (Verweise in der Datenbank), siehe MediaRename.
 */
class MediaRenameCronjob extends rex_cronjob
{
    public function execute(): bool
    {
        MediaRename::work(max(5, (int) $this->getParam('seconds', 50)));
        $this->setMessage(rex_i18n::msg(MediaRename::hasPending() ? 'mediaplace_cronjob_rename_pending' : 'mediaplace_cronjob_rename_idle'));

        return true;
    }

    public function getTypeName(): string
    {
        return rex_i18n::msg('mediaplace_cronjob_rename');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getParamFields(): array
    {
        return [[
            'label' => rex_i18n::msg('mediaplace_cronjob_rename_seconds'),
            'name' => 'seconds',
            'type' => 'text',
            'default' => '50',
        ]];
    }
}
