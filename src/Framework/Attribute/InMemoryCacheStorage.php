<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use function array_keys;
use function str_starts_with;
use function time;

final class InMemoryCacheStorage implements CacheStorageInterface
{
    /** @var array<string,array{result:mixed,expires:int}> */
    private array $cache = [];

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // @infection-ignore-all — the > vs >= boundary is a single-second tie; not worth testing
        if ($this->cache[$key]['expires'] > time()) {
            return true;
        }

        unset($this->cache[$key]);
        return false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->cache[$key]['result'] : $default;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->cache[$key] = [
            'result' => $value,
            'expires' => time() + $ttl,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->cache[$key]);
    }

    public function clear(): void
    {
        $this->cache = [];
    }

    public function deleteByPrefix(string $prefix): void
    {
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
            }
        }
    }
}
