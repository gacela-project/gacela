<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigArgs\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigArgs\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use RuntimeException;
use function is_callable;

final class GacelaConfigFileFactory implements GacelaConfigFileFactoryInterface
{
    private string $appRootDir;

    private string $gacelaPhpConfigFilename;

    /** @var array<string,mixed> */
    private array $globalServices;

    private FileIoInterface $fileIo;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(
        string $appRootDir,
        string $gacelaPhpConfigFilename,
        array $globalServices,
        FileIoInterface $fileIo
    ) {
        $this->appRootDir = $appRootDir;
        $this->gacelaPhpConfigFilename = $gacelaPhpConfigFilename;
        $this->globalServices = $globalServices;
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
         *     config?: callable,
         *     mapping-interfaces?: callable,
         *     suffix-types?: callable,
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;

        $configBuilder = new ConfigBuilder();
        $configFromGlobalServicesFn = $configFromGlobalServices['config'] ?? null;
        if ($configFromGlobalServicesFn !== null) {
            $configFromGlobalServicesFn($configBuilder);
        }

        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $mappingInterfacesFn = $configFromGlobalServices['mapping-interfaces'] ?? null;
        if ($mappingInterfacesFn !== null) {
            $mappingInterfacesFn($mappingInterfacesBuilder, $this->globalServices);
        }

        $suffixTypesBuilder = new SuffixTypesBuilder();
        $suffixTypesFn = $configFromGlobalServices['suffix-types'] ?? null;
        if ($suffixTypesFn !== null) {
            $suffixTypesFn($suffixTypesBuilder);
        }

        return $this->createWithDefaultIfEmpty($configBuilder, $mappingInterfacesBuilder, $suffixTypesBuilder);
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

        $configBuilder = new ConfigBuilder();
        $configGacelaClass->config($configBuilder);

        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $configGacelaClass->mappingInterfaces($mappingInterfacesBuilder, $this->globalServices);

        $suffixTypesBuilder = new SuffixTypesBuilder();
        $configGacelaClass->suffixTypes($suffixTypesBuilder);

        return $this->createWithDefaultIfEmpty($configBuilder, $mappingInterfacesBuilder, $suffixTypesBuilder);
    }

    private function createWithDefaultIfEmpty(
        ConfigBuilder $configBuilder,
        MappingInterfacesBuilder $mappingInterfacesBuilder,
        SuffixTypesBuilder $suffixTypesBuilder
    ): GacelaConfigFile {
        $gacelaConfigFile = GacelaConfigFile::withDefaults();

        $gacelaConfigFile->setConfigItems($configBuilder->build());
        $gacelaConfigFile->setMappingInterfaces($mappingInterfacesBuilder->build());
        $gacelaConfigFile->setSuffixTypes($suffixTypesBuilder->build());

        return $gacelaConfigFile;
    }
}
