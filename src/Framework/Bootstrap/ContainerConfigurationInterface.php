<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;

/**
 * Provides configuration for the dependency injection container.
 *
 * This interface defines the contract for configuring services, factories,
 * aliases, and contextual bindings within the DI container.
 */
interface ContainerConfigurationInterface
{
    /**
     * Get services that should be extended with decorators.
     *
     * @return array<string,list<Closure>>
     */
    public function getServicesToExtend(): array;

    /**
     * Get factory definitions for creating services.
     *
     * @return array<string,Closure>
     */
    public function getFactories(): array;

    /**
     * Get services that should be protected (not shared/singleton).
     *
     * @return array<string,Closure>
     */
    public function getProtectedServices(): array;

    /**
     * Get service ID aliases.
     *
     * @return array<string,string>
     */
    public function getAliases(): array;

    /**
     * Get contextual bindings for dependency resolution.
     *
     * Contextual bindings allow different implementations to be injected
     * based on the context (which class is requesting the dependency).
     *
     * @return array<string,array<class-string,class-string|callable|object>>
     */
    public function getContextualBindings(): array;

    /**
     * Get lazy-loaded service definitions.
     *
     * Lazy services are only instantiated when first accessed,
     * improving startup performance for expensive services.
     *
     * @return array<string,Closure>
     */
    public function getLazyServices(): array;
}
