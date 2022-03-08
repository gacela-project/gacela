<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesResolver;
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
            return $this->createGacelaConfigFromBootstrap();
        }

        return $this->createGacelaConfigUsingGacelaPhpFile($gacelaPhpPath);
    }

    private function createGacelaConfigFromBootstrap(): GacelaConfigFile
    {
        /**
         * @var array{
         *     config?: list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string}>|array{path?:string, path_local?:string, reader?:ConfigReaderInterface|class-string},
         *     mapping-interfaces?: callable,
         *     override-resolvable-types?: callable
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;
        $configItems = isset($configFromGlobalServices['config'])
            ? $this->configGacelaMapper->mapConfigItems($configFromGlobalServices['config'])
            : [];

        $interfacesResolver = new MappingInterfacesResolver();
        $configFromGlobalServicesFn = $configFromGlobalServices['mapping-interfaces'] ?? null;
        if ($configFromGlobalServicesFn !== null) {
            $configFromGlobalServicesFn($interfacesResolver);
        }

        $resolvableTypesConfig = new ResolvableTypesConfig();
        $configFromGlobalServicesFn = $configFromGlobalServices['override-resolvable-types'] ?? null;
        if ($configFromGlobalServicesFn !== null) {
            $configFromGlobalServicesFn($resolvableTypesConfig);
        }

        return $this->createWithDefaultIfEmpty($configItems, $interfacesResolver, $resolvableTypesConfig);
    }

    public function createGacelaConfigUsingGacelaPhpFile(string $gacelaPhpPath): GacelaConfigFile
    {
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

        $interfacesResolver = new MappingInterfacesResolver();
        $configGacelaClass->mappingInterfaces($interfacesResolver, $this->globalServices);

        $resolvableTypesConfig = new ResolvableTypesConfig();
        $configGacelaClass->overrideResolvableTypes($resolvableTypesConfig);

        return $this->createWithDefaultIfEmpty($configItems, $interfacesResolver, $resolvableTypesConfig);
    }

    /**
     * @param list<GacelaConfigItem> $configItems
     */
    private function createWithDefaultIfEmpty(
        array $configItems,
        MappingInterfacesResolver $interfacesResolver,
        ResolvableTypesConfig $overrideResolvableTypes
    ): GacelaConfigFile {
        $gacelaConfigFile = GacelaConfigFile::withDefaults();

        if (!empty($configItems)) {
            $gacelaConfigFile->setConfigItems($configItems);
        }
        $gacelaConfigFile->setMappingInterfaces($interfacesResolver->resolve());
        $gacelaConfigFile->setOverrideResolvableTypes($overrideResolvableTypes->resolve());

        return $gacelaConfigFile;
    }
}
