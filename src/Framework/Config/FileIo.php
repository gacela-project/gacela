<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Override;

final class FileIo implements FileIoInterface
{
    #[Override]
    public function existsFile(string $filePath): bool
    {
        return file_exists($filePath);
    }

    #[Override]
    public function include(string $filePath): mixed
    {
        /** @psalm-suppress UnresolvableInclude */
        return include $filePath;
    }
}
