<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

/**
 * Pluggable backend for #[Cacheable] method results.
 *
 * Implementations can adapt any store (in-memory, APCu, Redis, PSR-16, ...).
 * Register a custom backend via CacheableConfig::setStorage().
 */
interface CacheStorageInterface
{
    public function has(string $key): bool;

    /**
     * Returns $default when $key is not present or has expired.
     * The default is used as a miss-sentinel by CacheableTrait to avoid a
     * separate has()+get() round-trip on the hot path.
     */
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttl): void;

    public function delete(string $key): void;

    public function clear(): void;

    public function deleteByPrefix(string $prefix): void;
}
