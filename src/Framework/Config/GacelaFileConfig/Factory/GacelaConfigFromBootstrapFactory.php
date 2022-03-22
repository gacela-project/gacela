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

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    /** @var array<string,mixed> */
    private array $globalServices;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(array $globalServices)
    {
        $this->globalServices = $globalServices;
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        return GacelaConfigFile::usingBuilders(
            $this->createConfigBuilder(),
            $this->createMappingInterfacesBuilder(),
            $this->createSuffixTypesBuilder(),
            $this->isResolvableClassNamesCacheEnabled()
        );
    }

    private function createConfigBuilder(): ConfigBuilder
    {
        /** @var array{config?: callable} $configFromGlobalServices */
        $configFromGlobalServices = $this->globalServices;

        $configBuilder = new ConfigBuilder();
        $configFromGlobalServicesFn = $configFromGlobalServices['config'] ?? null;
        if (null !== $configFromGlobalServicesFn) {
            $configFromGlobalServicesFn($configBuilder);
        }

        return $configBuilder;
    }

    private function createMappingInterfacesBuilder(): MappingInterfacesBuilder
    {
        /** @var array{mapping-interfaces?: callable} $configFromGlobalServices */
        $configFromGlobalServices = $this->globalServices;

        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $mappingInterfacesFn = $configFromGlobalServices['mapping-interfaces'] ?? null;
        if (null !== $mappingInterfacesFn) {
            $mappingInterfacesFn($mappingInterfacesBuilder, $this->globalServices);
        }

        return $mappingInterfacesBuilder;
    }

    private function createSuffixTypesBuilder(): SuffixTypesBuilder
    {
        /** @var array{suffix-types?: callable} $configFromGlobalServices */
        $configFromGlobalServices = $this->globalServices;
        $suffixTypesBuilder = new SuffixTypesBuilder();
        $suffixTypesFn = $configFromGlobalServices['suffix-types'] ?? null;
        if (null !== $suffixTypesFn) {
            $suffixTypesFn($suffixTypesBuilder);
        }

        return $suffixTypesBuilder;
    }

    private function isResolvableClassNamesCacheEnabled(): bool
    {
        /** @var array{resolvable-class-names-cache-enabled?: bool} $globalServices */
        $globalServices = $this->globalServices;

        return $globalServices['resolvable-class-names-cache-enabled']
            ?? AbstractConfigGacela::DEFAULT_RESOLVABLE_CLASS_NAMES_CACHE_ENABLED;
    }
}
