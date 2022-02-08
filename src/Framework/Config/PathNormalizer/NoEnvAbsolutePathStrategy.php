<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

final class NoEnvAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    private string $appRootDir;

    public function __construct(string $appRootDir)
    {
        $this->appRootDir = $appRootDir;
    }

    public function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            rtrim($this->appRootDir, '/'),
            ltrim($relativePath, '/')
        );
    }
}
