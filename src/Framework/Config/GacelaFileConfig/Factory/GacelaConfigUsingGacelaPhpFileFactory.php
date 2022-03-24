<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use RuntimeException;
use function is_callable;

final class GacelaConfigUsingGacelaPhpFileFactory implements GacelaConfigFileFactoryInterface
{
    private string $gacelaPhpPath;

    /** @var array<string,mixed> */
    private array $setup;

    private FileIoInterface $fileIo;

    /**
     * @param array<string,mixed> $setup
     */
    public function __construct(
        string $gacelaPhpPath,
        array $setup,
        FileIoInterface $fileIo
    ) {
        $this->gacelaPhpPath = $gacelaPhpPath;
        $this->setup = $setup;
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

        $configBuilder = $this->createConfigBuilder($configGacelaClass);
        $mappingInterfacesBuilder = $this->createMappingInterfacesBuilder($configGacelaClass);
        $suffixTypesBuilder = $this->createSuffixTypesBuilder($configGacelaClass);

        return GacelaConfigFile::usingBuilders($configBuilder, $mappingInterfacesBuilder, $suffixTypesBuilder);
    }

    private function createConfigBuilder(AbstractConfigGacela $configGacelaClass): ConfigBuilder
    {
        $configBuilder = new ConfigBuilder();
        $configGacelaClass->config($configBuilder);

        return $configBuilder;
    }

    private function createMappingInterfacesBuilder(AbstractConfigGacela $configGacelaClass): MappingInterfacesBuilder
    {
        /** @var array{global-services?: array<string,mixed>} $setup */
        $setup = $this->setup;

        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $globalServicesFallback = $setup; // @deprecated, the fallback will be an empty array in the next version
        # $globalServicesFallback = []; // Replacement for the deprecated version
        $configGacelaClass->mappingInterfaces($mappingInterfacesBuilder, $setup['global-services'] ?? $globalServicesFallback);

        return $mappingInterfacesBuilder;
    }

    private function createSuffixTypesBuilder(AbstractConfigGacela $configGacelaClass): SuffixTypesBuilder
    {
        $suffixTypesBuilder = new SuffixTypesBuilder();
        $configGacelaClass->suffixTypes($suffixTypesBuilder);

        return $suffixTypesBuilder;
    }
}
