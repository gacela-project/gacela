<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

final class SuffixAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    private string $applicationRootDir;

    private string $configFileNameSuffix;

    public function __construct(
        string $applicationRootDir,
        string $configFileNameSuffix = ''
    ) {
        $this->applicationRootDir = $applicationRootDir;
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
            $relativePathWithFileSuffix = $relativePath . $this->getConfigFileNameSuffix();
        } else {
            $relativePathWithFileSuffix = $relativePath;
        }

        return sprintf(
            '%s/%s',
            $this->applicationRootDir,
            $relativePathWithFileSuffix
        );
    }

    private function getConfigFileNameSuffix(): string
    {
        return $this->configFileNameSuffix;
    }
}
