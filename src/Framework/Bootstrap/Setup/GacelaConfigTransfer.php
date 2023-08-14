<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class GacelaConfigTransfer
{
    /**
     * @param ?array<string, class-string|object|callable> $externalServices
     * @param ?list<string> $projectNamespaces
     * @param ?array<string,mixed> $configKeyValues
     * @param ?list<callable> $genericListeners
     * @param ?array<class-string,list<callable>> $specificListeners
     * @param ?list<class-string> $gacelaConfigsToExtend
     * @param ?list<class-string|callable> $plugins
     * @param ?array<string,list<Closure>> $servicesToExtend
     */
    public function __construct(
        private AppConfigBuilder $appConfigBuilder,
        private SuffixTypesBuilder $suffixTypesBuilder,
        private BindingsBuilder $bindingsBuilder,
        private ?array $externalServices,
        private ?bool $shouldResetInMemoryCache,
        private ?bool $fileCacheEnabled,
        private ?string $fileCacheDirectory,
        private ?array $projectNamespaces,
        private ?array $configKeyValues,
        private ?array $genericListeners,
        private ?array $specificListeners,
        private ?bool $areEventListenersEnabled,
        private ?array $gacelaConfigsToExtend,
        private ?array $plugins,
        private ?array $servicesToExtend,
    ) {
    }

    public function getAppConfigBuilder(): AppConfigBuilder
    {
        return $this->appConfigBuilder;
    }

    public function getSuffixTypesBuilder(): SuffixTypesBuilder
    {
        return $this->suffixTypesBuilder;
    }

    public function getBindingsBuilder(): BindingsBuilder
    {
        return $this->bindingsBuilder;
    }

    /**
     * @return ?array<string, class-string|object|callable>
     */
    public function getExternalServices(): ?array
    {
        return $this->externalServices;
    }

    public function getShouldResetInMemoryCache(): ?bool
    {
        return $this->shouldResetInMemoryCache;
    }

    public function getFileCacheEnabled(): ?bool
    {
        return $this->fileCacheEnabled;
    }

    public function getFileCacheDirectory(): ?string
    {
        return $this->fileCacheDirectory;
    }

    /**
     * @return ?list<string>
     */
    public function getProjectNamespaces(): ?array
    {
        return $this->projectNamespaces;
    }

    /**
     * @return ?array<string,mixed>
     */
    public function getConfigKeyValues(): ?array
    {
        return $this->configKeyValues;
    }

    public function getAreEventListenersEnabled(): ?bool
    {
        return $this->areEventListenersEnabled;
    }

    /**
     * @return ?list<callable>
     */
    public function getGenericListeners(): ?array
    {
        return $this->genericListeners;
    }

    /**
     * @return ?array<class-string,list<callable>>
     */
    public function getSpecificListeners(): ?array
    {
        return $this->specificListeners;
    }

    /**
     * @return ?list<class-string>
     */
    public function getGacelaConfigsToExtend(): ?array
    {
        return $this->gacelaConfigsToExtend;
    }

    /**
     * @return ?list<class-string|callable>
     */
    public function getPlugins(): ?array
    {
        return $this->plugins;
    }

    /**
     * @return ?array<string,list<Closure>>
     */
    public function getServicesToExtend(): ?array
    {
        return $this->servicesToExtend;
    }
}
