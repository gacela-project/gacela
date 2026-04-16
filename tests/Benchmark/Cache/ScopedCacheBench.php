<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;

use function bin2hex;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

/**
 * @BeforeMethods({"setUp"})
 *
 * @AfterMethods({"tearDown"})
 *
 * @Revs(50)
 *
 * @Iterations(5)
 */
final class ScopedCacheBench
{
    private string $cacheDir;

    public function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-scoped-bench-' . bin2hex(random_bytes(4));
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function tearDown(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        /** @var list<string> $entries */
        $entries = scandir($this->cacheDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $this->cacheDir . '/' . $entry;
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->cacheDir);
    }

    /**
     * Baseline: put/get through the ScopedCache decorator with no dependencies.
     * Measures the thin-wrapper overhead vs raw FileCache.
     */
    public function bench_put_and_get_no_dependencies(): void
    {
        $cache = new ScopedCache(new FileCache($this->cacheDir));

        for ($i = 0; $i < 20; ++$i) {
            $cache->put('key' . $i, 'value' . $i);
        }

        for ($i = 0; $i < 20; ++$i) {
            $cache->get('key' . $i);
        }
    }

    /**
     * Linear chain: key0 ← key1 ← key2 ← … ← key19.
     * Each dependsOn() triggers cycle-detection BFS of increasing depth.
     */
    public function bench_depends_on_linear_chain(): void
    {
        $cache = new ScopedCache(new FileCache($this->cacheDir));

        for ($i = 0; $i < 20; ++$i) {
            $cache->put('key' . $i, 'value' . $i);
        }

        for ($i = 1; $i < 20; ++$i) {
            $cache->dependsOn('key' . $i, 'key' . ($i - 1));
        }
    }

    /**
     * Wide graph: 20 children all depend on one root.
     * Tests addEdge + persistGraph overhead with many siblings.
     */
    public function bench_depends_on_wide_graph(): void
    {
        $cache = new ScopedCache(new FileCache($this->cacheDir));
        $cache->put('root', 'root-value');

        for ($i = 0; $i < 20; ++$i) {
            $cache->put('child' . $i, 'child-value' . $i);
            $cache->dependsOn('child' . $i, 'root');
        }
    }

    /**
     * Invalidate a root with 19 transitive descendants (linear chain).
     * Measures BFS collection + cascading cache deletes + graph persist.
     */
    public function bench_invalidate_cascade(): void
    {
        $cache = new ScopedCache(new FileCache($this->cacheDir));

        for ($i = 0; $i < 20; ++$i) {
            $cache->put('key' . $i, 'value' . $i);
        }

        for ($i = 1; $i < 20; ++$i) {
            $cache->dependsOn('key' . $i, 'key' . ($i - 1));
        }

        $cache->invalidate('key0');
    }

    /**
     * Invalidate a leaf node — no cascade, just single-entry removal.
     */
    public function bench_invalidate_leaf(): void
    {
        $cache = new ScopedCache(new FileCache($this->cacheDir));

        for ($i = 0; $i < 20; ++$i) {
            $cache->put('key' . $i, 'value' . $i);
        }

        for ($i = 1; $i < 20; ++$i) {
            $cache->dependsOn('key' . $i, 'key' . ($i - 1));
        }

        $cache->invalidateLeaf('key19');
    }
}
