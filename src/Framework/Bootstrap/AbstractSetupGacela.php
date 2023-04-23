<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

abstract class AbstractSetupGacela implements SetupGacelaInterface
{
    /**
     * Define different config sources.
     */
    public function buildConfig(ConfigBuilder $builder): ConfigBuilder
    {
        return $builder;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string, class-string|object|callable> $externalServices
     */
    public function buildMappingInterfaces(MappingInterfacesBuilder $builder, array $externalServices): MappingInterfacesBuilder
    {
        return $builder;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder
    {
        return $builder;
    }

    /**
     * @return array<string, class-string|object|callable>
     */
    public function externalServices(): array
    {
        return [];
    }
}
