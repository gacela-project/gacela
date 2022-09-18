<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\Cache;

final class CacheClearer
{
    private string $cacheDir;
    private DirectoryIoInterface $io;

    public function __construct(string $cacheDir, DirectoryIoInterface $io)
    {
        $this->cacheDir = $cacheDir;
        $this->io = $io;
    }

    /**
     * @return list<string> the removed file paths
     */
    public function clearCacheFiles(): array
    {
        return $this->io->removeDir($this->cacheDir);
    }
}
