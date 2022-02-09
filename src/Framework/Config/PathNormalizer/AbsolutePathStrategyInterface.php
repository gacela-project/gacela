<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

interface AbsolutePathStrategyInterface
{
    public function generateAbsolutePath(string $relativePath): string;
}
