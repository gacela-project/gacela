<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * Provides configuration builders for Gacela framework setup.
 *
 * This interface defines the contract for building application configuration,
 * service bindings, and suffix types used by the class resolver.
 */
interface BuilderConfigurationInterface
{
    /**
     * Define different config sources.
     *
     * This method allows customization of how configuration files are loaded
     * and processed. You can add config readers, set paths, and configure
     * the configuration loading behavior.
     */
    public function buildAppConfig(AppConfigBuilder $builder): AppConfigBuilder;

    /**
     * Define the mapping between interfaces and concretions.
     *
     * This allows Gacela services to auto-resolve dependencies automatically
     * by binding interfaces to their concrete implementations.
     *
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function buildBindings(
        BindingsBuilder $builder,
        array $externalServices,
    ): BindingsBuilder;

    /**
     * Allow overriding Gacela resolvable types.
     *
     * Customize the suffix patterns used by Gacela's class resolver
     * (e.g., Factory, Config, DependencyProvider).
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder;

    /**
     * Get external services available for dependency injection.
     *
     * External services are objects or class names that can be injected
     * into Gacela managed classes.
     *
     * @return array<string,class-string|object|callable>
     */
    public function externalServices(): array;
}
