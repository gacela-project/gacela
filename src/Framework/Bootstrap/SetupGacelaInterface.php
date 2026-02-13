<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

/**
 * Main configuration interface for Gacela framework setup.
 *
 * This interface extends specialized configuration interfaces to provide
 * a complete configuration contract for the Gacela framework.
 */
interface SetupGacelaInterface extends BuilderConfigurationInterface, ContainerConfigurationInterface, CacheConfigurationInterface
{
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

    public function merge(SetupGacela $other): self;

    /**
     * @return list<class-string>
     */
    public function getGacelaConfigsToExtend(): array;

    /**
     * @return list<class-string|callable>
     */
    public function getPlugins(): array;
}
