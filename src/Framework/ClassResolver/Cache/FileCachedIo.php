<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\Shared\FileIoInterface;

final class FileCachedIo implements FileCachedIoInterface
{
    private FileIoInterface $io;

    public function __construct(FileIoInterface $io)
    {
        $this->io = $io;
    }

    public function existsCacheFile(string $filename): bool
    {
        return $this->io->existsFile($filename);
    }

    /**
     * @return array<string,string>
     */
    public function readCacheFile(string $filename): array
    {
        $fileContent = $this->io->fileGetContents($filename);

        /** @var array<string,string> $decoded */
        $decoded = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string,string> $data
     */
    public function writeCachedData(string $filename, array $data): void
    {
        $fileContent = json_encode($data, JSON_THROW_ON_ERROR);

        $this->io->writeFile($filename, $fileContent);
    }
}
