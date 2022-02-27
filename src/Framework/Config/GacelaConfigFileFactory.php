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
        $mappingInterfaces = $configGacelaClass->mappingInterfaces($this->globalServices);

        return $this->createWithDefaultIfEmpty($configItems, $mappingInterfaces);
    }

    private function createDefaultGacelaPhpConfig(): GacelaConfigFile
    {
        /**
         * @var array{
         *     config?: list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface}>|array{path?:string, path_local?:string, reader?:ConfigReaderInterface},
         *     mapping-interfaces?: array<class-string,class-string|callable>,
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;
        $configItems = $this->configGacelaMapper->mapConfigItems($configFromGlobalServices['config'] ?? []);
        $mappingInterfaces = $configFromGlobalServices['mapping-interfaces'] ?? [];

        return $this->createWithDefaultIfEmpty($configItems, $mappingInterfaces);
    }

    /**
     * @param list<GacelaConfigItem> $configItems
     * @param array<class-string,class-string|callable> $mappingInterfaces
     */
    private function createWithDefaultIfEmpty(
        array $configItems,
        array $mappingInterfaces
    ): GacelaConfigFile {
        $gacelaConfigFile = GacelaConfigFile::withDefaults();

        if (!empty($configItems)) {
            $gacelaConfigFile->setConfigItems($configItems);
        }
        if (!empty($mappingInterfaces)) {
            $gacelaConfigFile->setMappingInterfaces($mappingInterfaces);
        }

        return $gacelaConfigFile;
    }
}
