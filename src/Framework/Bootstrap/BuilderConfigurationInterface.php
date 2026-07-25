<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * @psalm-type ExternalServicesMap = array<string, class-string|object|callable>
 */
interface BuilderConfigurationInterface
{
    /**
     * Define different config sources.
     */
    public function buildAppConfig(AppConfigBuilder $builder): AppConfigBuilder;

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param ExternalServicesMap $externalServices
     */
    public function buildBindings(
        BindingsBuilder $builder,
        array $externalServices,
    ): BindingsBuilder;

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder;

    /**
     * @return ExternalServicesMap
     */
    public function externalServices(): array;
}
