<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class ConfigInit
{
    private string $applicationRootDir;

    private GacelaJsonConfigFactoryInterface $configFactory;

    private PathFinderInterface $pathFinder;

    /** @var array<string,ConfigReaderInterface> */
    private array $readers;

    /**
     * @param array<string,ConfigReaderInterface> $readers
     */
    public function __construct(
        string $applicationRootDir,
        GacelaJsonConfigFactoryInterface $configFactory,
        PathFinderInterface $pathFinder,
        array $readers
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->configFactory = $configFactory;
        $this->pathFinder = $pathFinder;
        $this->readers = $readers;
    }

    public function readAll(): array
    {
        $gacelaJsonConfig = $this->configFactory->createGacelaJsonConfig();
        $configs = [];

        foreach ($this->scanAllConfigFiles($gacelaJsonConfig) as $absolutePath) {
            $configs[] = $this->readConfigFromFile($gacelaJsonConfig, $absolutePath);
        }

        $configs[] = $this->readLocalConfigFile($gacelaJsonConfig);

        return array_merge(...$configs);
    }

    /**
     * @return string[]
     */
    private function scanAllConfigFiles(GacelaJsonConfig $gacelaJsonConfig): array
    {
        $configGroup = array_map(
            fn (GacelaJsonConfigItem $config): array => array_map(
                static fn ($p): string => (string)$p,
                array_diff(
                    $this->pathFinder->matchingPattern($this->generateAbsolutePath($config->path())),
                    [$this->generateAbsolutePath($config->pathLocal())]
                )
            ),
            $gacelaJsonConfig->configs()
        );

        return array_merge(...array_values($configGroup));
    }

    private function readConfigFromFile(GacelaJsonConfig $gacelaJson, string $absolutePath): array
    {
        $result = [];
        $configs = $gacelaJson->configs();

        foreach ($this->readers as $type => $reader) {
            $config = $configs[$type] ?? null;
            if ($config === null) {
                continue;
            }

            $result[] = $reader->canRead($absolutePath)
                ? $reader->read($absolutePath)
                : [];
        }

        return array_merge(...array_filter($result));
    }

    private function readLocalConfigFile(GacelaJsonConfig $gacelaJson): array
    {
        $result = [];
        $configs = $gacelaJson->configs();

        foreach ($this->readers as $type => $reader) {
            $config = $configs[$type] ?? null;
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

    private function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            $this->applicationRootDir,
            $relativePath
        );
    }
}
