<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface ConfigReaderInterface
{
    /**
     * @return array<array-key, string>
     */
    public function read(string $absolutePath): array;

    public function canRead(string $absolutePath): bool;
}
