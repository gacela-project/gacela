<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ServiceMapMigration;

use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use OuterIterator;
use SplFileInfo;

use function file_get_contents;
use function str_contains;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * Runs {@see ServiceMapMigrator} over the files a project actually scans.
 *
 * The same paths module discovery walks, so `migrate:service-map` covers what
 * `doctor` and `list:modules` cover -- a migration that read a different set
 * than the analysis would leave the build failing on files it never saw.
 *
 * Writing is the caller's choice, not this one's: a preview and a real run
 * compute the identical result and differ only in whether it is saved.
 */
final class ServiceMapMigrationRunner
{
    /**
     * @param OuterIterator<array-key, SplFileInfo> $fileIterator
     */
    public function __construct(
        private readonly OuterIterator $fileIterator,
        private readonly ServiceMapMigrator $migrator,
        private readonly FileContentIoInterface $fileContentIo,
    ) {
    }

    /**
     * @return list<MigrationResult> only the files that would change
     */
    public function run(string $filter, bool $dryRun): array
    {
        $changed = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($this->fileIterator as $fileInfo) {
            $path = $this->readablePhpFile($fileInfo);
            if ($path === null) {
                continue;
            }

            if ($filter !== '' && !str_contains($path, $filter)) {
                continue;
            }

            $code = file_get_contents($path);
            if ($code === false) {
                continue;
            }

            $result = $this->migrator->migrate($path, $code);
            if (!$result->hasChanges()) {
                continue;
            }

            if (!$dryRun) {
                $this->fileContentIo->filePutContents($path, $result->migratedCode);
            }

            $changed[] = $result;
        }

        return $changed;
    }

    private function readablePhpFile(SplFileInfo $fileInfo): ?string
    {
        $realPath = $fileInfo->getRealPath();

        if ($realPath === false
            || !$fileInfo->isFile()
            || $fileInfo->getExtension() !== 'php'
            || str_starts_with($fileInfo->getFilename(), '.')
            || str_contains($realPath, 'vendor' . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        return $realPath;
    }
}
