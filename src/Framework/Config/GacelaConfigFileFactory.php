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
    private string $appRootDir;

    private string $gacelaPhpConfigFilename;

    /** @var array<string,mixed> */
    private array $globalServices;

    private ConfigGacelaMapper $configGacelaMapper;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(
        string $appRootDir,
        string $gacelaPhpConfigFilename,
        array $globalServices,
        ConfigGacelaMapper $configGacelaMapper
    ) {
        $this->appRootDir = $appRootDir;
        $this->gacelaPhpConfigFilename = $gacelaPhpConfigFilename;
        $this->globalServices = $globalServices;
        $this->configGacelaMapper = $configGacelaMapper;
    }

    public function createGacelaFileConfig(): GacelaConfigFile
    {
        $gacelaPhpPath = $this->appRootDir . '/' . $this->gacelaPhpConfigFilename;

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
        $flexibleServices = $configGacelaClass->flexibleServices();

        return $this->createWithDefaultIfEmpty($configItems, $configReaders, $mappingInterfaces, $flexibleServices);
    }

    private function createDefaultGacelaPhpConfig(): GacelaConfigFile
    {
        /** @var array{
         *     config?: array<array>|array{type:string,path:string,path_local:string},
         *     config-readers?: array<string,ConfigReaderInterface>,
         *     mapping-interfaces?: array<class-string,class-string|callable>,
         *     flexible-services?: array{paths?:list<string>,resolvable-types?:list<string>},
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;
        $configItems = $this->configGacelaMapper->mapConfigItems($configFromGlobalServices['config'] ?? []);
        $configReaders = $configFromGlobalServices['config-readers'] ?? [];
        $mappingInterfaces = $configFromGlobalServices['mapping-interfaces'] ?? [];
        $flexibleServices = $configFromGlobalServices['flexible-services'] ?? [];

        return $this->createWithDefaultIfEmpty($configItems, $configReaders, $mappingInterfaces, $flexibleServices);
    }

    /**
     * @param list<GacelaConfigItem> $configItems
     * @param array<string,ConfigReaderInterface> $configReaders
     * @param array<class-string,class-string|callable> $mappingInterfaces
     * @param array{paths?:list<string>,resolvable-types?:list<string>} $flexibleServices
     */
    private function createWithDefaultIfEmpty(
        array $configItems,
        array $configReaders,
        array $mappingInterfaces,
        array $flexibleServices
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
        if (!empty($flexibleServices)) {
            $gacelaConfigFile->setFlexibleServices($flexibleServices);
        }

        return $gacelaConfigFile;
    }
}
