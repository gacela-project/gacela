<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface ConfigReaderInterface
{
    public function canRead(string $absolutePath): bool;

    /**
     * @return array<string,mixed>
     */
    public function read(string $absolutePath): array;
}
