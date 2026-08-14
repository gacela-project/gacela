<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use function dirname;
use function in_array;
use function is_file;

/**
 * Copies the built-in stubs into the project, where they can be edited.
 *
 * Refusing to overwrite is the default because a published stub is a file
 * somebody changed on purpose: the one thing this must never do is quietly
 * throw that away.
 */
final class StubPublisher
{
    /**
     * @param array<string, string> $builtIn stub file => contents
     */
    public function __construct(
        private readonly FileContentIoInterface $io,
        private readonly array $builtIn,
    ) {
    }

    /**
     * @param list<string> $only the stub files to publish; every one when empty
     * @param bool $dryRun work out the same answer and write none of it. The
     *                     result reports what *would* be written, decided by
     *                     the same rules -- a preview resolved any other way
     *                     could disagree with the run it previews
     */
    public function publish(
        string $stubsDir,
        array $only = [],
        bool $force = false,
        bool $dryRun = false,
    ): StubPublishResult {
        $written = [];
        $skipped = [];

        foreach ($this->builtIn as $stubFile => $contents) {
            if ($only !== [] && !in_array($stubFile, $only, true)) {
                continue;
            }

            $path = $stubsDir . '/' . $stubFile;
            if (!$force && is_file($path)) {
                $skipped[] = $path;
                continue;
            }

            if (!$dryRun) {
                $this->io->mkdir(dirname($path));
                $this->io->filePutContents($path, $contents);
            }

            $written[] = $path;
        }

        return new StubPublishResult($written, $skipped);
    }
}
