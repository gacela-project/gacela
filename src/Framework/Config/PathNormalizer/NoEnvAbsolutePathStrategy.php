<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

final class NoEnvAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    private string $applicationRootDir;

    public function __construct(string $applicationRootDir)
    {
        $this->applicationRootDir = $applicationRootDir;
    }

    public function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            $this->applicationRootDir,
            $relativePath
        );
    }
}
