<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

interface SetupGacelaInterface
{
    /**
     * Define different config sources.
     */
    public function buildConfig(ConfigBuilder $configBuilder): ConfigBuilder;

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function buildMappingInterfaces(MappingInterfacesBuilder $mappingInterfacesBuilder, array $externalServices): MappingInterfacesBuilder;

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $suffixTypesBuilder): SuffixTypesBuilder;

    /**
     * Define global services that can be accessible via the mapping interfaces.
     *
     * @return array<string,class-string|object|callable>
     */
    public function externalServices(): array;

    public function isResetCache(): bool;
}
