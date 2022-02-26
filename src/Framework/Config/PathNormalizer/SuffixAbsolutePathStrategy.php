<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

final class SuffixAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    private string $appRootDir;

    private string $configFileNameSuffix;

    public function __construct(
        string $appRootDir,
        string $configFileNameSuffix = ''
    ) {
        $this->appRootDir = $appRootDir;
        $this->configFileNameSuffix = $configFileNameSuffix;
    }

    public function generateAbsolutePath(string $relativePath): string
    {
        $suffix = $this->configFileNameSuffix;
        if ($suffix === '') {
            return '';
        }

        // place the file suffix right before the file extension
        $dotPos = strpos($relativePath, '.');

        if ($dotPos !== false) {
            $relativePathWithFileSuffix = substr($relativePath, 0, $dotPos)
                . '-' . $suffix
                . substr($relativePath, $dotPos);
        } else {
            $relativePathWithFileSuffix = $relativePath . '-' . $suffix;
        }

        return sprintf(
            '%s/%s',
            rtrim($this->appRootDir, '/'),
            ltrim($relativePathWithFileSuffix, '/')
        );
    }
}
