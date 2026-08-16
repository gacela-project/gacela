<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Bootstrap;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\InMemoryCacheStorage;

/**
 * Gives `#[Cacheable]` a store the application owns.
 *
 * Without this, the framework lends one that lives and dies with the process --
 * fine for a worker, and a cache that never hits under PHP-FPM. `doctor` says
 * so, which is why registering a backend is part of booting rather than
 * something a deployment remembers.
 *
 * A real deployment would hand over a Redis-backed `CacheStorageInterface`
 * here; an application with in-memory repositories has nothing to outlive.
 */
final class RegisterCacheableStorage
{
    public function __invoke(): void
    {
        CacheableConfig::setStorage(new InMemoryCacheStorage());
    }
}
