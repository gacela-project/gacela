<?php

declare(strict_types=1);

namespace Gacela\Framework\Testing;

/**
 * Immutable value object capturing a snapshot of Gacela container-related
 * singletons and caches so tests can restore state between methods.
 *
 * The snapshot is intentionally restricted to data that is safe to serialize
 * (scalars, arrays, and cache-dir roots). It does not attempt to capture
 * object instances from the container because those may hold non-serializable
 * resources (closures, file handles, PDO connections, etc.).
 */
final class ContainerSnapshot
{
    /**
     * @param  array<string, array<string, string>>  $inMemoryCache
     * @param  array<string, mixed>                  $config
     * @param  array<string, mixed>                  $extras
     */
    public function __construct(
        private readonly array $inMemoryCache = [],
        private readonly array $config = [],
        private readonly ?string $appRootDir = null,
        private readonly ?string $cacheDir = null,
        private readonly array $extras = [],
    ) {
    }

    /**
     * @return array{
     *     inMemoryCache: array<string, array<string, string>>,
     *     config: array<string, mixed>,
     *     appRootDir: ?string,
     *     cacheDir: ?string,
     *     extras: array<string, mixed>,
     * }
     */
    public function __serialize(): array
    {
        return [
            'inMemoryCache' => $this->inMemoryCache,
            'config' => $this->config,
            'appRootDir' => $this->appRootDir,
            'cacheDir' => $this->cacheDir,
            'extras' => $this->extras,
        ];
    }

    /**
     * @param  array{
     *     inMemoryCache?: array<string, array<string, string>>,
     *     config?: array<string, mixed>,
     *     appRootDir?: ?string,
     *     cacheDir?: ?string,
     *     extras?: array<string, mixed>,
     * }  $data
     */
    public function __unserialize(array $data): void
    {
        /** @psalm-suppress InaccessibleProperty */
        $this->inMemoryCache = $data['inMemoryCache'] ?? [];
        /** @psalm-suppress InaccessibleProperty */
        $this->config = $data['config'] ?? [];
        /** @psalm-suppress InaccessibleProperty */
        $this->appRootDir = $data['appRootDir'] ?? null;
        /** @psalm-suppress InaccessibleProperty */
        $this->cacheDir = $data['cacheDir'] ?? null;
        /** @psalm-suppress InaccessibleProperty */
        $this->extras = $data['extras'] ?? [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function inMemoryCache(): array
    {
        return $this->inMemoryCache;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function appRootDir(): ?string
    {
        return $this->appRootDir;
    }

    public function cacheDir(): ?string
    {
        return $this->cacheDir;
    }

    /**
     * @return array<string, mixed>
     */
    public function extras(): array
    {
        return $this->extras;
    }
}
