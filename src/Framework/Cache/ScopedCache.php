<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function array_filter;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_file;

use const DIRECTORY_SEPARATOR;

/**
 * Dependency-aware decorator over {@see FileCache}.
 *
 * Each entry may declare other entries it depends on via {@see dependsOn()}.
 * Invalidating a parent cascades through every transitive dependent;
 * {@see invalidateLeaf()} invalidates a single entry without cascading.
 *
 * The dependency graph is persisted next to the underlying {@see FileCache}
 * directory, so the relationships survive process restarts. Cycles are
 * rejected eagerly at {@see dependsOn()} time.
 *
 * Concurrency: this decorator assumes a single writer at a time. Multiple
 * concurrent writers may lose edges added between the in-memory load and
 * the on-disk persist. The value store behind it ({@see FileCache}) remains
 * read-safe under concurrency regardless.
 *
 * @template T
 */
final class ScopedCache
{
    private const GRAPH_FILENAME = '.gacela-scoped-cache-graph.php';

    /** @var array<string, list<string>> childKey => list of direct parents */
    private array $dependencies = [];

    /** @var array<string, list<string>> parentKey => list of direct dependents */
    private array $dependents = [];

    /**
     * @param FileCache<T> $cache
     */
    public function __construct(
        private readonly FileCache $cache,
    ) {
        $this->loadGraph();
    }

    /**
     * @return T|null
     */
    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    /**
     * @param T $value
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->cache->put($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * Declare that `$childKey`'s validity depends on `$parentKey`.
     * Invalidating `$parentKey` transitively invalidates `$childKey`.
     *
     * @throws CycleDetectedException when the new edge would close a cycle
     */
    public function dependsOn(string $childKey, string $parentKey): void
    {
        if ($childKey === $parentKey) {
            throw CycleDetectedException::selfDependency($childKey);
        }

        if ($this->reachableViaDependencies($parentKey, $childKey)) {
            throw CycleDetectedException::between($childKey, $parentKey);
        }

        if (!in_array($parentKey, $this->dependencies[$childKey] ?? [], true)) {
            $this->dependencies[$childKey][] = $parentKey;
        }

        if (!in_array($childKey, $this->dependents[$parentKey] ?? [], true)) {
            $this->dependents[$parentKey][] = $childKey;
        }

        $this->persistGraph();
    }

    /**
     * Invalidate `$key` and every entry that transitively declared a
     * dependency on it. Runs in O(|descendants|), not O(|entries|).
     */
    public function invalidate(string $key): void
    {
        $toForget = $this->collectDescendants($key);
        $toForget[] = $key;

        foreach (array_unique($toForget) as $k) {
            $this->cache->forget($k);
            $this->removeFromGraph($k);
        }

        $this->persistGraph();
    }

    /**
     * Invalidate `$key` without cascading to its dependents. Useful when the
     * caller knows dependents are still valid (e.g. a file changed but the
     * namespace it participates in did not).
     */
    public function invalidateLeaf(string $key): void
    {
        $this->cache->forget($key);
        $this->removeFromGraph($key);
        $this->persistGraph();
    }

    /**
     * Direct dependents of `$key` (one hop). Use {@see invalidate()} for the
     * transitive set.
     *
     * @return list<string>
     */
    public function dependents(string $key): array
    {
        return $this->dependents[$key] ?? [];
    }

    /**
     * @return list<string>
     */
    private function collectDescendants(string $key): array
    {
        $out = [];
        $queue = [$key];
        $seen = [$key => true];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->dependents[$current] ?? [] as $child) {
                if (isset($seen[$child])) {
                    continue;
                }
                $seen[$child] = true;
                $out[] = $child;
                $queue[] = $child;
            }
        }

        return $out;
    }

    /**
     * Walks the forward (child => parents) graph. Returns true when `$target`
     * is reachable from `$start` — i.e. when `$start` already depends on
     * `$target`, directly or transitively.
     */
    private function reachableViaDependencies(string $start, string $target): bool
    {
        $queue = [$start];
        $seen = [$start => true];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->dependencies[$current] ?? [] as $parent) {
                if ($parent === $target) {
                    return true;
                }
                if (isset($seen[$parent])) {
                    continue;
                }
                $seen[$parent] = true;
                $queue[] = $parent;
            }
        }

        return false;
    }

    private function removeFromGraph(string $key): void
    {
        foreach ($this->dependencies[$key] ?? [] as $parent) {
            $this->dependents[$parent] = array_values(array_filter(
                $this->dependents[$parent] ?? [],
                static fn (string $c): bool => $c !== $key,
            ));
            if ($this->dependents[$parent] === []) {
                unset($this->dependents[$parent]);
            }
        }

        foreach ($this->dependents[$key] ?? [] as $child) {
            $this->dependencies[$child] = array_values(array_filter(
                $this->dependencies[$child] ?? [],
                static fn (string $p): bool => $p !== $key,
            ));
            if ($this->dependencies[$child] === []) {
                unset($this->dependencies[$child]);
            }
        }

        unset($this->dependencies[$key], $this->dependents[$key]);
    }

    private function graphPath(): string
    {
        return $this->cache->directory . DIRECTORY_SEPARATOR . self::GRAPH_FILENAME;
    }

    private function loadGraph(): void
    {
        $path = $this->graphPath();
        if (!is_file($path)) {
            return;
        }

        /** @var mixed $payload */
        $payload = require $path;
        if (!is_array($payload)) {
            return;
        }

        /** @var array<string, list<string>> $deps */
        $deps = is_array($payload['dependencies'] ?? null) ? $payload['dependencies'] : [];
        /** @var array<string, list<string>> $dependents */
        $dependents = is_array($payload['dependents'] ?? null) ? $payload['dependents'] : [];

        $this->dependencies = $deps;
        $this->dependents = $dependents;
    }

    private function persistGraph(): void
    {
        FileCache::writeAtomically($this->graphPath(), [
            'dependencies' => $this->dependencies,
            'dependents' => $this->dependents,
        ]);
    }
}
