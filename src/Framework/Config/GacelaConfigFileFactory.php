<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use RuntimeException;
use function is_callable;

final class GacelaConfigFileFactory implements GacelaConfigFileFactoryInterface
{
    private string $applicationRootDir;

    private string $gacelaPhpConfigFilename;

    /** @var array<string,mixed> */
    private array $globalServices;

    private ConfigGacelaMapper $configGacelaMapper;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(
        string $applicationRootDir,
        string $gacelaPhpConfigFilename,
        array $globalServices,
        ConfigGacelaMapper $configGacelaMapper
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->gacelaPhpConfigFilename = $gacelaPhpConfigFilename;
        $this->globalServices = $globalServices;
        $this->configGacelaMapper = $configGacelaMapper;
    }

    public function createGacelaFileConfig(): GacelaConfigFile
    {
        $gacelaPhpPath = $this->applicationRootDir . '/' . $this->gacelaPhpConfigFilename;

        if (!is_file($gacelaPhpPath)) {
            return $this->createDefaultGacelaPhpConfig();
        }

        $configGacela = include $gacelaPhpPath;
        if (!is_callable($configGacela)) {
            throw new RuntimeException('Create a function that returns an anonymous class that extends AbstractConfigGacela');
        }

        /** @var AbstractConfigGacela $configGacelaClass */
        $configGacelaClass = $configGacela();
        if (!is_subclass_of($configGacelaClass, AbstractConfigGacela::class)) {
            throw new RuntimeException('Your anonymous class must extends AbstractConfigGacela');
        }

        $configItems = $this->configGacelaMapper->mapConfigItems($configGacelaClass->config());
        $configReaders = $configGacelaClass->configReaders();
        $mappingInterfaces = $configGacelaClass->mappingInterfaces($this->globalServices);

        return $this->createWithDefaultIfEmpty($configItems, $configReaders, $mappingInterfaces);
    }

    private function createDefaultGacelaPhpConfig(): GacelaConfigFile
    {
        /** @var array{
         *     config?: array<array>|array{type:string,path:string,path_local:string},
         *     config-readers?: array<string,ConfigReaderInterface>,
         *     mapping-interfaces?: array<class-string,class-string|callable>,
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;
        $configItems = $this->configGacelaMapper->mapConfigItems($configFromGlobalServices['config'] ?? []);
        $configReaders = $configFromGlobalServices['config-readers'] ?? [];
        $mappingInterfaces = $configFromGlobalServices['mapping-interfaces'] ?? [];

        return $this->createWithDefaultIfEmpty($configItems, $configReaders, $mappingInterfaces);
    }

    /**
     * @param array<string,GacelaConfigItem> $configItems
     * @param array<string,ConfigReaderInterface> $configReaders
     * @param array<class-string,class-string|callable> $mappingInterfaces
     */
    private function createWithDefaultIfEmpty(
        array $configItems,
        array $configReaders,
        array $mappingInterfaces
    ): GacelaConfigFile {
        $gacelaConfigFile = GacelaConfigFile::withDefaults();

        if (!empty($configItems)) {
            $gacelaConfigFile->setConfigItems($configItems);
        }
        if (!empty($configReaders)) {
            $gacelaConfigFile->setConfigReaders($configReaders);
        }
        if (!empty($mappingInterfaces)) {
            $gacelaConfigFile->setMappingInterfaces($mappingInterfaces);
        }

        return $gacelaConfigFile;
    }
}
