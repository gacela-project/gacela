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
        /** @var array<string,string> $array */
        $array = $this->io->include($filename);

        return $array;
    }

    /**
     * @param array<string,string> $data
     */
    public function writeCachedData(string $filename, array $data): void
    {
        $fileContent = sprintf('<?php return %s;', var_export($data, true));

        $this->io->writeFile($filename, $fileContent);
    }
}
