<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface ConfigReaderInterface
{
    public function read(string $fullPath): array;
}
