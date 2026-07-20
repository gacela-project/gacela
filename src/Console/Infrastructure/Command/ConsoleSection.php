<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;
use function str_repeat;

/**
 * Renders the shared "section" banner used across the Gacela console commands,
 * keeping the width and styling defined in a single place.
 */
final class ConsoleSection
{
    private const WIDTH = 60;

    public static function title(OutputInterface $output, string $title): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $title));
        self::separator($output);
        $output->writeln('');
    }

    public static function separator(OutputInterface $output): void
    {
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', self::WIDTH)));
    }
}
