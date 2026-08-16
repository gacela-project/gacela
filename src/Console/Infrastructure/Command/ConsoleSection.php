<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function implode;
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

    /**
     * Why a module-listing run found nothing, in one place so every command
     * that discovers modules answers in the same words.
     *
     * A filter that matched nothing and a project where nothing is a module are
     * different answers, and `No modules match filter ""` gave the second in
     * the words of the first -- quoting a filter the reader never typed.
     *
     * The empty case names the cause worth naming: discovery reflects on the
     * class to see whether it descends from `AbstractFacade`, so a Facade whose
     * namespace composer cannot map is skipped in silence. The files are right
     * there on disk, which is what makes an empty list read as a bug in the
     * command rather than in the autoload map.
     *
     * Both answers name the paths that were scanned. `appModulePaths` narrows
     * discovery to a subset of the project, so a module outside that subset is
     * absent for a reason no amount of looking at the module itself reveals.
     *
     * @param list<string> $scannedPaths
     */
    public static function noModulesFound(
        OutputInterface $output,
        string $filter,
        string $indent = '',
        array $scannedPaths = [],
    ): void {
        if ($filter !== '') {
            $output->writeln(sprintf('%s<comment>No modules match filter "%s".</comment>', $indent, $filter));
            self::writeScannedPaths($output, $indent, $scannedPaths);

            return;
        }

        $output->writeln(sprintf('%s<comment>No modules found.</comment>', $indent));
        self::writeScannedPaths($output, $indent, $scannedPaths);
        $output->writeln(sprintf(
            '%sA module is found by its Facade: the filename carries the suffix, and the class has to be'
            . ' autoloadable. If the files are there, check the psr-4 mapping in composer.json.',
            $indent,
        ));
    }

    /**
     * What a `--dry-run` would write.
     *
     * "would replace" is said separately from "would create" because they are
     * different answers: one of them loses whatever is in the file, and a
     * preview that called both "would write" would hide exactly the case
     * somebody runs a preview to check.
     *
     * @param list<array{path: string, exists: bool}> $planned
     */
    public static function plannedFiles(OutputInterface $output, array $planned): void
    {
        foreach ($planned as $file) {
            $output->writeln($file['exists']
                ? sprintf("> Would <fg=yellow>replace</> '%s'", $file['path'])
                : sprintf("> Would create '%s'", $file['path']));
        }

        $output->writeln(sprintf(
            '<comment>Dry run: nothing was written (%d file%s).</comment>',
            count($planned),
            count($planned) === 1 ? '' : 's',
        ));
    }

    /**
     * Why a `make:*` run wrote nothing, in one place so both makers refuse in
     * the same words.
     *
     * @param list<string> $paths
     */
    public static function refusedToOverwrite(OutputInterface $output, array $paths): void
    {
        $output->writeln(sprintf(
            '<error>%s already %s.</error>',
            implode(', ', $paths),
            count($paths) === 1 ? 'exists' : 'exist',
        ));
        $output->writeln(sprintf(
            'Nothing was written. Pass <comment>--force</comment> to replace %s.',
            count($paths) === 1 ? 'it' : 'them',
        ));
    }

    /**
     * @param list<string> $scannedPaths
     */
    private static function writeScannedPaths(OutputInterface $output, string $indent, array $scannedPaths): void
    {
        if ($scannedPaths === []) {
            return;
        }

        $output->writeln(sprintf('%sScanned: %s', $indent, implode(', ', $scannedPaths)));
    }
}
