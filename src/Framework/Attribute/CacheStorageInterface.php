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

    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl): void;

    public function delete(string $key): void;

    public function clear(): void;

    public function deleteByPrefix(string $prefix): void;
}
