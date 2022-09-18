<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure;

use FilesystemIterator;
use Gacela\Console\Domain\Cache\DirectoryIoInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @codeCoverageIgnore
 */
final class DirectoryIo implements DirectoryIoInterface
{
    /**
     * @return list<string>
     */
    public function removeDir(string $target): array
    {
        if (!is_dir($target)) {
            return [];
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $filenames = [];
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (is_dir($file->getPathname())) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
                $filenames[] = $file->getPathname();
            }
        }

        rmdir($target);

        return $filenames;
    }
}
