<?php

declare(strict_types=1);

namespace Gacela\Framework\Setup;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

abstract class AbstractSetupGacela implements SetupGacelaInterface
{
    /**
     * Define different config sources.
     */
    public function config(ConfigBuilder $configBuilder): void
    {
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,mixed> $globalServices
     */
    public function mappingInterfaces(MappingInterfacesBuilder $mappingInterfacesBuilder, array $globalServices): void
    {
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function suffixTypes(SuffixTypesBuilder $suffixTypesBuilder): void
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function globalServices(): array
    {
        return [];
    }
}
