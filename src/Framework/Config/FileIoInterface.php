<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface FileIoInterface
{
    public function existsFile(string $filePath): bool;

    public function include(string $filePath): mixed;
}
