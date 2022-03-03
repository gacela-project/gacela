<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface FileIoInterface
{
    public function existsFile(string $filePath): bool;

    /**
     * @return mixed
     */
    public function include(string $filePath);
}
