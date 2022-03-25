<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    /** @var array<string,mixed> */
    private array $setup;

    /**
     * @param array<string,mixed> $setup
     */
    public function __construct(array $setup)
    {
        $this->setup = $setup;
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $configBuilder = $this->createConfigBuilder();
        $mappingInterfacesBuilder = $this->createMappingInterfacesBuilder();
        $suffixTypesBuilder = $this->createSuffixTypesBuilder();

        return GacelaConfigFile::usingBuilders($configBuilder, $mappingInterfacesBuilder, $suffixTypesBuilder);
    }

    private function createConfigBuilder(): ConfigBuilder
    {
        /** @var array{config?: callable} $setup */
        $setup = $this->setup;

        $configBuilder = new ConfigBuilder();
        $configFromSetupFn = $setup['config'] ?? null;
        if (null !== $configFromSetupFn) {
            $configFromSetupFn($configBuilder);
        }

        return $configBuilder;
    }

    private function createMappingInterfacesBuilder(): MappingInterfacesBuilder
    {
        /** @var array{mapping-interfaces?: callable, global-services?: array<string,mixed>} $setup */
        $setup = $this->setup;

        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $mappingInterfacesFn = $setup['mapping-interfaces'] ?? null;
        if (null !== $mappingInterfacesFn) {
            $globalServicesFallback = $setup; // @deprecated, the fallback will be an empty array in the next version
            # $globalServicesFallback = []; // Replacement for the deprecated version
            $mappingInterfacesFn($mappingInterfacesBuilder, $setup['global-services'] ?? $globalServicesFallback);
        }

        return $mappingInterfacesBuilder;
    }

    private function createSuffixTypesBuilder(): SuffixTypesBuilder
    {
        /** @var array{suffix-types?: callable} $setup */
        $setup = $this->setup;
        $suffixTypesBuilder = new SuffixTypesBuilder();
        $suffixTypesFn = $setup['suffix-types'] ?? null;
        if (null !== $suffixTypesFn) {
            $suffixTypesFn($suffixTypesBuilder);
        }

        return $suffixTypesBuilder;
    }
}
