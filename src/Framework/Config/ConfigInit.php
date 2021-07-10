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

        return array_merge(...$configGroup);
    }

    private function readConfigFromFile(GacelaJsonConfig $gacelaJson, string $absolutePath): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $result[] = $this->readConfigItem($config, $absolutePath);
        }

        return array_merge(...array_filter($result));
    }

    private function readLocalConfigFile(GacelaJsonConfig $gacelaJson): array
    {
        $result = [];

        foreach ($gacelaJson->configs() as $config) {
            $absolutePath = $this->generateAbsolutePath($config->pathLocal());

            $result[] = $this->readConfigItem($config, $absolutePath);
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

    private function readConfigItem(GacelaJsonConfigItem $config, string $absolutePath): array
    {
        $reader = $this->readers[$config->type()] ?? null;

        if ($reader === null) {
            throw ConfigReaderException::notSupported($config->type(), $this->readers);
        }

        if ($reader->canRead($absolutePath)) {
            return $reader->read($absolutePath);
        }

        return [];
    }
}
