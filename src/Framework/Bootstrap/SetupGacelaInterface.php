<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

interface SetupGacelaInterface
{
    /**
     * Define different config sources.
     */
    public function buildAppConfig(AppConfigBuilder $builder): AppConfigBuilder;

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string, class-string|object|callable> $externalServices
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
     * Define global services that can be accessible via the mapping interfaces.
     *
     * @return array<string, class-string|object|callable>
     */
    public function externalServices(): array;

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

    public function getEventDispatcher(): EventDispatcherInterface;

    public function combine(SetupGacela $other): self;

    /**
     * @return array<string,list<Closure>>
     */
    public function getServicesToExtend(): array;

    /**
     * @return list<class-string>
     */
    public function getGacelaConfigsToExtend(): array;

    /**
     * @return list<class-string|callable>
     */
    public function getPlugins(): array;
}
