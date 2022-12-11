<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

final class WithoutSuffixAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    public function __construct(
        private string $appRootDir,
    ) {
    }

    public function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            rtrim($this->appRootDir, '/'),
            ltrim($relativePath, '/'),
        );
    }
}
