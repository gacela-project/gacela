<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\GacelaConfigArgs\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigArgs\SuffixTypesResolver;

abstract class AbstractConfigGacela
{
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
    public function suffixTypes(SuffixTypesResolver $suffixTypesResolver): void
    {
    }
}
