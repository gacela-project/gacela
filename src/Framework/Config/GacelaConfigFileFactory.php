<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigArgs\ResolvableTypesConfig;
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

    private ConfigGacelaMapperInterface $configGacelaMapper;

    private FileIoInterface $fileIo;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(
        string $appRootDir,
        string $gacelaPhpConfigFilename,
        array $globalServices,
        ConfigGacelaMapperInterface $configGacelaMapper,
        FileIoInterface $fileIo
    ) {
        $this->appRootDir = $appRootDir;
        $this->gacelaPhpConfigFilename = $gacelaPhpConfigFilename;
        $this->globalServices = $globalServices;
        $this->configGacelaMapper = $configGacelaMapper;
        $this->fileIo = $fileIo;
    }

    public function createGacelaFileConfig(): GacelaConfigFile
    {
        $gacelaPhpPath = $this->appRootDir . '/' . $this->gacelaPhpConfigFilename;

        if (!$this->fileIo->existsFile($gacelaPhpPath)) {
            return $this->createDefaultGacelaPhpConfig();
        }

        $configGacela = $this->fileIo->include($gacelaPhpPath);
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

        $resolvableTypesConfig = new ResolvableTypesConfig();
        $configGacelaClass->overrideResolvableTypes($resolvableTypesConfig);

        return $this->createWithDefaultIfEmpty($configItems, $mappingInterfaces, $resolvableTypesConfig);
    }

    private function createDefaultGacelaPhpConfig(): GacelaConfigFile
    {
        /**
         * @var array{
         *     config?: list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string}>|array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string},
         *     mapping-interfaces?: array<class-string,class-string|callable>,
         *     override-resolvable-types?: callable
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;
        $configItems = isset($configFromGlobalServices['config'])
            ? $this->configGacelaMapper->mapConfigItems($configFromGlobalServices['config'])
            : [];
        $mappingInterfaces = $configFromGlobalServices['mapping-interfaces'] ?? [];

        $resolvableTypesConfig = new ResolvableTypesConfig();
        $configFromGlobalServicesFn = $configFromGlobalServices['override-resolvable-types'] ?? null;
        if ($configFromGlobalServicesFn !== null) {
            $configFromGlobalServicesFn($resolvableTypesConfig);
        }

        return $this->createWithDefaultIfEmpty($configItems, $mappingInterfaces, $resolvableTypesConfig);
    }

    /**
     * @param list<GacelaConfigItem> $configItems
     * @param array<class-string,class-string|callable> $mappingInterfaces
     * @param ResolvableTypesConfig $overrideResolvableTypes
     */
    private function createWithDefaultIfEmpty(
        array $configItems,
        array $mappingInterfaces,
        ResolvableTypesConfig $overrideResolvableTypes
    ): GacelaConfigFile {
        $gacelaConfigFile = GacelaConfigFile::withDefaults();

        if (!empty($configItems)) {
            $gacelaConfigFile->setConfigItems($configItems);
        }
        if (!empty($mappingInterfaces)) {
            $gacelaConfigFile->setMappingInterfaces($mappingInterfaces);
        }
        $gacelaConfigFile->setOverrideResolvableTypes($overrideResolvableTypes->resolve());

        return $gacelaConfigFile;
    }
}
