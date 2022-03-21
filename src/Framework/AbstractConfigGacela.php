<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

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
    public function suffixTypes(SuffixTypesBuilder $suffixTypesBuilder): void
    {
    }

    /**
     * Set caching the gacela resolvable class names to improve the performance of the class finder.
     */
    public function isResolvableClassNamesCacheEnabled(): bool
    {
        return true;
    }
}
