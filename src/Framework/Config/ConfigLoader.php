<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;

final class ConfigLoader
{
    private string $applicationRootDir;

    private GacelaConfigFileFactoryInterface $configFactory;

    private PathFinderInterface $pathFinder;

    private string $configFileNameSuffix;

    public function __construct(
        string $applicationRootDir,
        GacelaConfigFileFactoryInterface $configFactory,
        PathFinderInterface $pathFinder,
        string $configFileNameSuffix = ''
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->configFactory = $configFactory;
        $this->pathFinder = $pathFinder;
        $this->configFileNameSuffix = $configFileNameSuffix;
    }

    /**
     * @return array<string,mixed>
     */
    public function loadAll(): array
    {
        $gacelaFileConfig = $this->configFactory->createGacelaFileConfig();
        $configs = [];

        foreach ($this->scanAllConfigFiles($gacelaFileConfig) as $absolutePath) {
            $configs[] = $this->readConfigFromFile($gacelaFileConfig, $absolutePath);
        }

        $configs[] = $this->readLocalConfigFile($gacelaFileConfig);

        return array_merge(...$configs);
    }

    /**
     * All config files except the local config file.
     *
     * @return iterable<string>
     */
    private function scanAllConfigFiles(GacelaConfigFile $gacelaFileConfig): iterable
    {
        $configGroup = [];
        foreach ($gacelaFileConfig->getConfigItems() as $configItem) {
            $absolutePath = $this->generateAbsolutePathWithSuffix($configItem->path());
            $matchingPattern = $this->pathFinder->matchingPattern($absolutePath);
            $excludePattern = [$this->generateAbsolutePath($configItem->pathLocal())];

            $configGroup[] = array_diff($matchingPattern, $excludePattern);
        }

        foreach (array_merge(...$configGroup) as $path) {
            yield $path;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readConfigFromFile(GacelaConfigFile $gacelaConfigFile, string $absolutePath): array
    {
        $result = [];
        $configItems = $gacelaConfigFile->getConfigItems();

        foreach ($gacelaConfigFile->getConfigReaders() as $type => $reader) {
            $config = $configItems[$type] ?? null;
            if ($config === null) {
                continue;
            }

            $result[] = $reader->canRead($absolutePath)
                ? $reader->read($absolutePath)
                : [];
        }

        return array_merge(...array_filter($result));
    }

    /**
     * @return array<string,mixed>
     */
    private function readLocalConfigFile(GacelaConfigFile $gacelaConfigFile): array
    {
        $result = [];
        $configItems = $gacelaConfigFile->getConfigItems();

        foreach ($gacelaConfigFile->getConfigReaders() as $type => $reader) {
            $config = $configItems[$type] ?? null;
            if ($config === null) {
                continue;
            }
            $absolutePath = $this->generateAbsolutePath($config->pathLocal());

            $result[] = $reader->canRead($absolutePath)
                ? $reader->read($absolutePath)
                : [];
        }

        return array_merge(...array_filter($result));
    }

    private function generateAbsolutePathWithSuffix(string $relativePath): string
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

        return $this->generateAbsolutePath($relativePathWithFileSuffix);
    }

    private function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            $this->applicationRootDir,
            $relativePath
        );
    }

    private function getConfigFileNameSuffix(): string
    {
        return $this->configFileNameSuffix;
    }
}
