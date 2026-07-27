<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

/**
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 *
 * @psalm-type ServiceFactoryMap = array<string, Closure>
 * @psalm-type ServiceAliasMap = array<string, string>
 * @psalm-type ServicesToExtendMap = array<string, list<Closure>>
 * @psalm-type HandlerRegistriesMap = array<string, array<string|int, class-string>>
 * @psalm-type TagsMap = array<string, list<string>>
 * @psalm-type AfterResolvingMap = array<string, list<Closure>>
 * @psalm-type ContextualBindingsMap = array<string, array<string, mixed>>
 */
interface ContainerConfigurationInterface
{
    /**
     * @return ServicesToExtendMap
     */
    public function getServicesToExtend(): array;

    /**
     * @return ServiceFactoryMap
     */
    public function getFactories(): array;

    /**
     * Closures stored as-is: the container returns them uninvoked, and they cannot be extended.
     *
     * @return ServiceFactoryMap
     */
    public function getProtectedServices(): array;

    /**
     * @return ServiceAliasMap
     */
    public function getAliases(): array;

    /**
     * Bindings selected by which class is requesting the dependency.
     *
     * @return ContextualBindingsMap
     */
    public function getContextualBindings(): array;

    /**
     * Build-time dispatch tables: each entry maps a registry identifier to its
     * declared handler classes, and is resolvable from the container under that identifier.
     *
     * @return HandlerRegistriesMap
     */
    public function getHandlerRegistries(): array;

    /**
     * Service identifiers grouped under a label, resolvable together through
     * `Container::tagged()`. Unkeyed, where a handler registry is keyed: a tag
     * answers "every implementation of this", not "the one for this key".
     *
     * @return TagsMap
     */
    public function getTags(): array;

    /**
     * Callbacks to run on a resolved instance, keyed by the id they match. The
     * id may name a concrete class or an interface, so the match is made
     * against the instance rather than by looking the requested id up.
     *
     * @return AfterResolvingMap
     */
    public function getAfterResolvingCallbacks(): array;

    /**
     * Services instantiated on first access rather than at container build.
     *
     * @return ServiceFactoryMap
     */
    public function getLazyServices(): array;
}
