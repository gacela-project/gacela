<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use Gacela\Console\Domain\CommandArguments\CommandArguments;

interface FileContentGeneratorInterface
{
    /**
     * @param string $subDirectory optional sub-directory (relative to the module dir) to place the file in
     *
     * @return string path result where the file was generated
     */
    public function generate(CommandArguments $commandArguments, string $filename, bool $withShortName = false, string $subDirectory = ''): string;

    /**
     * Where `generate()` would write, without writing it.
     */
    public function targetPath(CommandArguments $commandArguments, string $filename, bool $withShortName, string $subDirectory): string;

    /**
     * Which of the given targets already exist on disk.
     *
     * Asked of every file a command is about to write before it writes any of
     * them: a module half replaced is worse than one refused, the same reason
     * an unusable path is rejected before generation rather than during it.
     *
     * @param list<array{string, string}> $files [filename, subDirectory] pairs
     *
     * @return list<string> the paths that exist, in the order given
     */
    public function existingTargets(CommandArguments $commandArguments, array $files, bool $withShortName): array;
}
