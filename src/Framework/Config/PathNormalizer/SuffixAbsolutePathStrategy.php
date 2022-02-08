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
        // place the file suffix right before the file extension
        $dotPos = strpos($relativePath, '.');
        $suffix = $this->getConfigFileNameSuffix();

        if ($dotPos !== false && !empty($suffix)) {
            $relativePathWithFileSuffix = substr($relativePath, 0, $dotPos)
                . '-' . $this->getConfigFileNameSuffix()
                . substr($relativePath, $dotPos);
        } elseif (!empty($suffix)) {
            $relativePathWithFileSuffix = $relativePath . '-' . $suffix;
        } else {
            $relativePathWithFileSuffix = $relativePath;
        }

        return sprintf(
            '%s/%s',
            rtrim($this->appRootDir, '/'),
            ltrim($relativePathWithFileSuffix, '/')
        );
    }

    private function getConfigFileNameSuffix(): string
    {
        return $this->configFileNameSuffix;
    }
}
