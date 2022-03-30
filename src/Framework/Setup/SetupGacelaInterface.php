<?php

declare(strict_types=1);

namespace Gacela\Framework\Setup;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

interface SetupGacelaInterface
{
    /**
     * Define different config sources.
     */
    public function config(ConfigBuilder $configBuilder): void;

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,mixed> $externalServices
     */
    public function mappingInterfaces(MappingInterfacesBuilder $mappingInterfacesBuilder, array $externalServices): void;

    /**
     * Allow overriding gacela resolvable types.
     */
    public function suffixTypes(SuffixTypesBuilder $suffixTypesBuilder): void;

    /**
     * Define global services that can be accessible via the mapping interfaces.
     *
     * @return array<string,mixed>
     */
    public function externalServices(): array;

    /**
     * @deprecated Use `externalServices()` instead
     *
     * @return array<string,mixed>
     */
    public function globalServices(): array;
}
