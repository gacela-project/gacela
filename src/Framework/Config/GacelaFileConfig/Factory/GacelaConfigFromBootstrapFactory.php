<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Gacela;

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
        $configFromSetupFn = $setup[Gacela::CONFIG] ?? null;
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
        $mappingInterfacesFn = $setup[Gacela::MAPPING_INTERFACES] ?? null;
        if (null !== $mappingInterfacesFn) {
            $globalServicesFallback = $setup; // @deprecated, the fallback will be an empty array in the next version
            # $globalServicesFallback = []; // Replacement for the deprecated version
            $mappingInterfacesFn($mappingInterfacesBuilder, $setup[Gacela::GLOBAL_SERVICES] ?? $globalServicesFallback);
        }

        return $mappingInterfacesBuilder;
    }

    private function createSuffixTypesBuilder(): SuffixTypesBuilder
    {
        /** @var array{suffix-types?: callable} $setup */
        $setup = $this->setup;
        $suffixTypesBuilder = new SuffixTypesBuilder();
        $suffixTypesFn = $setup[Gacela::SUFFIX_TYPES] ?? null;
        if (null !== $suffixTypesFn) {
            $suffixTypesFn($suffixTypesBuilder);
        }

        return $suffixTypesBuilder;
    }
}
