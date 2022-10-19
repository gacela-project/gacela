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
    public function buildConfig(ConfigBuilder $builder): ConfigBuilder;

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function buildMappingInterfaces(MappingInterfacesBuilder $builder, array $externalServices): MappingInterfacesBuilder;

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder;

    /**
     * Define global services that can be accessible via the mapping interfaces.
     *
     * @return array<string,class-string|object|callable>
     */
    public function externalServices(): array;

    /**
     * Get the profiler directory.
     */
    public function getProfilerDirectory(): string;

    /**
     * Enable resetting the memory cache on each setup. Useful for functional tests.
     */
    public function shouldResetInMemoryCache(): bool;

    /**
     * Get whether the file cache flag is enabled.
     */
    public function isFileCacheEnabled(): bool;

    /**
     * Get the file cache directory.
     */
    public function getFileCacheDirectory(): string;

    /**
     * Get whether the profiler is enabled.
     */
    public function isProfilerEnabled(): bool;

    /**
     * Get the list of project namespaces.
     *
     * @return list<string>
     */
    public function getProjectNamespaces(): array;

    /**
     * Get the list of key:value configuration.
     *
     * @return array<string,mixed>
     */
    public function getConfigKeyValues(): array;
}
