<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class FileIo implements FileIoInterface
{
    public function existsFile(string $filePath): bool
    {
        return file_exists($filePath);
    }

    public function include(string $filePath): mixed
    {
        /** @psalm-suppress UnresolvableInclude */
        return include $filePath;
    }
}
