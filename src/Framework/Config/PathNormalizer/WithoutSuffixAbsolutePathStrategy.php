<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

use Gacela\Framework\Cache\GlobLiteral;

use function sprintf;

final class WithoutSuffixAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    public function __construct(
        private readonly string $appRootDir,
    ) {
    }

    public function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            rtrim(GlobLiteral::escape($this->appRootDir), '/'),
            ltrim($relativePath, '/'),
        );
    }
}
