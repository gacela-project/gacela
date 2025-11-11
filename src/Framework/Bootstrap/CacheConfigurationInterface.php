<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

/**
 * Provides cache configuration for the Gacela framework.
 *
 * This interface defines the contract for configuring both in-memory
 * and file-based caching behavior.
 */
interface CacheConfigurationInterface
{
    /**
     * Check if file-based caching is enabled.
     *
     * When enabled, resolved class names and other data will be cached
     * to disk for faster subsequent loads.
     */
    public function isFileCacheEnabled(): bool;

    /**
     * Get the directory path for file cache storage.
     *
     * Returns the absolute path where cache files should be stored.
     */
    public function getFileCacheDirectory(): string;

    /**
     * Check if in-memory cache should be reset on bootstrap.
     *
     * When true, static in-memory caches will be cleared during
     * Gacela initialization. This is useful for testing or when
     * you need to ensure a clean state.
     */
    public function shouldResetInMemoryCache(): bool;
}
