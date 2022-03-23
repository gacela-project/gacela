<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Shared\FileIoInterface;
use RuntimeException;
use function is_callable;

final class GacelaConfigUsingGacelaPhpFileFactory implements GacelaConfigFileFactoryInterface
{
    private string $gacelaPhpPath;

    /** @var array<string,mixed> */
    private array $globalServices;

    private FileIoInterface $fileIo;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(
        string $gacelaPhpPath,
        array $globalServices,
        FileIoInterface $fileIo
    ) {
        $this->gacelaPhpPath = $gacelaPhpPath;
        $this->globalServices = $globalServices;
        $this->fileIo = $fileIo;
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $configGacela = $this->fileIo->include($this->gacelaPhpPath);
        if (!is_callable($configGacela)) {
            throw new RuntimeException('Create a function that returns an anonymous class that extends AbstractConfigGacela');
        }

        /** @var object $configGacelaClass */
        $configGacelaClass = $configGacela();
        if (!is_subclass_of($configGacelaClass, AbstractConfigGacela::class)) {
            throw new RuntimeException('Your anonymous class must extends AbstractConfigGacela');
        }

        return GacelaConfigFile::usingBuilders(
            $this->createConfigBuilder($configGacelaClass),
            $this->createMappingInterfacesBuilder($configGacelaClass),
            $this->createSuffixTypesBuilder($configGacelaClass),
            $configGacelaClass->isResolvableNamesCacheEnabled()
        );
    }

    private function createConfigBuilder(AbstractConfigGacela $configGacelaClass): ConfigBuilder
    {
        $configBuilder = new ConfigBuilder();
        $configGacelaClass->config($configBuilder);

        return $configBuilder;
    }

    private function createMappingInterfacesBuilder(AbstractConfigGacela $configGacelaClass): MappingInterfacesBuilder
    {
        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $configGacelaClass->mappingInterfaces($mappingInterfacesBuilder, $this->globalServices);

        return $mappingInterfacesBuilder;
    }

    private function createSuffixTypesBuilder(AbstractConfigGacela $configGacelaClass): SuffixTypesBuilder
    {
        $suffixTypesBuilder = new SuffixTypesBuilder();
        $configGacelaClass->suffixTypes($suffixTypesBuilder);

        return $suffixTypesBuilder;
    }
}
