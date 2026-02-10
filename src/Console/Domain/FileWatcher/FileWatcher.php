<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileWatcher;

use function filemtime;
use function glob;
use function sprintf;

final class FileWatcher
{
    /** @var array<string, int> */
    private array $fileTimestamps = [];

    /**
     * @param list<string> $watchPaths
     *
     * @return list<string>
     */
    public function detectChanges(array $watchPaths): array
    {
        $changedFiles = [];

        foreach ($watchPaths as $path) {
            $files = $this->getPhpFiles($path);

            foreach ($files as $file) {
                $currentTimestamp = filemtime($file);

                if (!isset($this->fileTimestamps[$file])) {
                    $this->fileTimestamps[$file] = $currentTimestamp;
                    continue;
                }

                if ($currentTimestamp !== $this->fileTimestamps[$file]) {
                    $changedFiles[] = $file;
                    $this->fileTimestamps[$file] = $currentTimestamp;
                }
            }
        }

        return $changedFiles;
    }

    /**
     * @param list<string> $watchPaths
     */
    public function initialize(array $watchPaths): void
    {
        foreach ($watchPaths as $path) {
            $files = $this->getPhpFiles($path);

            foreach ($files as $file) {
                $this->fileTimestamps[$file] = filemtime($file);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getPhpFiles(string $path): array
    {
        $pattern = sprintf('%s/**/*.php', $path);
        $files = glob($pattern, GLOB_BRACE);

        return $files !== false ? $files : [];
    }
}
