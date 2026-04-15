<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins;

/**
 * Build-time dispatch table that maps keys to handler classes.
 *
 * Implementations are frozen after boot: the handler map is declared
 * in a Provider via GacelaConfig::addHandlerRegistry() and no entries
 * can be added at runtime. Handlers are resolved through the DI
 * container so their constructor dependencies are auto-wired.
 *
 * @template THandler of object
 * @template TKey of string|int
 */
interface HandlerRegistry
{
    /**
     * Resolve (and cache) the handler registered for the given key.
     *
     * @return THandler
     */
    public function get(string|int $key): object;

    public function has(string|int $key): bool;

    /**
     * @return list<TKey>
     */
    public function keys(): array;
}
