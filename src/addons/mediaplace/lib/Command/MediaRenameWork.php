<?php

namespace FriendsOfRedaxo\Mediaplace\Command;

use FriendsOfRedaxo\Mediaplace\MediaRename;
use rex_console_command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MediaRenameWork extends rex_console_command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Setzt offene Umbenennungen fort und ersetzt die Verweise in der Datenbank')
            ->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Höchstdauer in Sekunden (0 = bis alles erledigt ist)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);
        $seconds = (int) $input->getOption('seconds');
        $deadline = $seconds > 0 ? microtime(true) + $seconds : PHP_FLOAT_MAX;
        while (MediaRename::hasPending() && microtime(true) < $deadline) {
            MediaRename::work(min(30, $deadline - microtime(true)));
        }
        MediaRename::hasPending()
            ? $io->warning('Es sind noch Umbenennungen offen.')
            : $io->success('Keine offenen Umbenennungen.');

        return 0;
    }
}
