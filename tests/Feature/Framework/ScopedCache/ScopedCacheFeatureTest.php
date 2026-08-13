<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ScopedCache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function count;
use function glob;
use function sys_get_temp_dir;
use function uniqid;

/**
 * End-to-end scenarios exercising {@see ScopedCache} against a real
 * {@see FileCache} on disk. Each test covers an angle of the holistic
 * lifecycle — cascading invalidation, leaf invalidation, process restart,
 * and full reset — so a future reader can see how the pieces compose.
 */
final class ScopedCacheFeatureTest extends TestCase
{
    private const GRAPH_FILE = '.gacela-scoped-cache-graph.php';

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-scoped-cache-feature-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->cacheDir);
    }

    /**
     * The graph is written on every `dependsOn()`, `invalidate()` and
     * `clear()`, and its path was the cache directory concatenated with the
     * filename. {@see FileCache} reduces `''`, `'/'` and whitespace to an
     * empty directory, so that concatenation named the filesystem root: a
     * cache with nowhere to write wrote and unlinked at `/` while holding
     * nothing on disk of its own.
     *
     * The values it decorates already degrade to memory; the graph now does
     * the same, through the helper {@see FileCache} reads.
     */
    public function test_a_cache_with_nowhere_to_write_keeps_its_graph_in_memory(): void
    {
        $scoped = new ScopedCache(new FileCache(''));

        $scoped->put('parent', 'p');
        $scoped->put('child', 'c');
        $scoped->dependsOn('child', 'parent');

        self::assertSame(['child'], $scoped->dependents('parent'));
        self::assertFileDoesNotExist(DIRECTORY_SEPARATOR . self::GRAPH_FILE);
        // Asked of the path, not only of the filesystem: a write to `/` fails
        // silently for an unprivileged process, so the file is absent whether
        // or not the bug is present. The path is the thing that differs.
        self::assertNull($this->graphPathOf($scoped));
    }

    /**
     * And a real directory still resolves to a path under it, so the guard
     * above cannot pass by answering null for everything.
     */
    public function test_a_real_directory_still_resolves_to_a_graph_path(): void
    {
        $scoped = new ScopedCache(new FileCache($this->cacheDir));

        self::assertSame(
            $this->cacheDir . DIRECTORY_SEPARATOR . self::GRAPH_FILE,
            $this->graphPathOf($scoped),
        );
    }

    /**
     * Every write path, because each persists: an invalidate and a clear would
     * each have reached the root on their own.
     */
    public function test_invalidating_and_clearing_with_nowhere_to_write_touch_no_disk(): void
    {
        $scoped = new ScopedCache(new FileCache(''));
        $scoped->put('parent', 'p');
        $scoped->dependsOn('child', 'parent');

        $scoped->invalidate('parent');
        $scoped->clear();

        self::assertNull($scoped->get('parent'));
        self::assertFileDoesNotExist(DIRECTORY_SEPARATOR . self::GRAPH_FILE);
    }

    /**
     * Scenario:
     *     ns:core ─── file:lib/a.php ──┬── fragment:a#1
     *                                  └── fragment:a#2
     *     ns:app  ─── file:app/ctrl.php ── fragment:ctrl#1
     *
     * Invalidating `ns:core` must drop every node under it while leaving
     * the `ns:app` subtree untouched.
     */
    public function test_invalidating_a_namespace_cascades_only_within_its_subtree(): void
    {
        $cache = $this->openCache();
        $this->seedPipeline($cache);

        $cache->invalidate('ns:core');

        self::assertFalse($cache->has('ns:core'));
        self::assertFalse($cache->has('file:lib/a.php'));
        self::assertFalse($cache->has('fragment:a#1'));
        self::assertFalse($cache->has('fragment:a#2'));

        self::assertSame('env:app', $cache->get('ns:app'));
        self::assertSame('compiled:ctrl', $cache->get('file:app/ctrl.php'));
        self::assertSame('frag:ctrl1', $cache->get('fragment:ctrl#1'));
    }

    public function test_graph_survives_process_restart_and_still_cascades(): void
    {
        $cache = $this->openCache();
        $this->seedPipeline($cache);

        // Simulate a fresh process.
        $reopened = $this->openCache();
        self::assertSame(['file:app/ctrl.php'], $reopened->dependents('ns:app'));

        $reopened->invalidate('ns:app');

        self::assertFalse($reopened->has('ns:app'));
        self::assertFalse($reopened->has('file:app/ctrl.php'));
        self::assertFalse($reopened->has('fragment:ctrl#1'));

        // The untouched subtree is still hot after the restart.
        self::assertSame('env:core', $reopened->get('ns:core'));
        self::assertSame('compiled:a', $reopened->get('file:lib/a.php'));
    }

    public function test_leaf_invalidation_drops_a_single_file_without_touching_neighbours(): void
    {
        $cache = $this->openCache();
        $this->seedPipeline($cache);

        $cache->invalidateLeaf('file:lib/a.php');

        self::assertFalse($cache->has('file:lib/a.php'));
        self::assertSame('frag:a1', $cache->get('fragment:a#1'));
        self::assertSame('frag:a2', $cache->get('fragment:a#2'));
        self::assertSame('env:core', $cache->get('ns:core'));

        // Edges pointing at the removed key are gone, in both directions.
        self::assertSame([], $cache->dependents('file:lib/a.php'));
        self::assertSame([], $cache->dependents('ns:core'));
    }

    public function test_clear_wipes_values_and_graph_file_and_survives_restart(): void
    {
        $cache = $this->openCache();
        $this->seedPipeline($cache);

        $graphPath = $this->cacheDir . '/' . self::GRAPH_FILE;
        self::assertFileExists($graphPath);
        self::assertGreaterThan(0, $this->countCacheFiles());

        $cache->clear();

        self::assertFileDoesNotExist($graphPath);
        self::assertSame(0, $this->countCacheFiles());

        $fresh = $this->openCache();
        self::assertSame([], $fresh->dependents('ns:core'));
        self::assertFalse($fresh->has('fragment:a#1'));
    }

    /**
     * @param ScopedCache<string> $cache
     */
    private function seedPipeline(ScopedCache $cache): void
    {
        $cache->put('ns:core', 'env:core');
        $cache->put('ns:app', 'env:app');
        $cache->put('file:lib/a.php', 'compiled:a');
        $cache->put('file:app/ctrl.php', 'compiled:ctrl');
        $cache->put('fragment:a#1', 'frag:a1');
        $cache->put('fragment:a#2', 'frag:a2');
        $cache->put('fragment:ctrl#1', 'frag:ctrl1');

        $cache->dependsOn('file:lib/a.php', 'ns:core');
        $cache->dependsOn('file:app/ctrl.php', 'ns:app');
        $cache->dependsOn('fragment:a#1', 'file:lib/a.php');
        $cache->dependsOn('fragment:a#2', 'file:lib/a.php');
        $cache->dependsOn('fragment:ctrl#1', 'file:app/ctrl.php');
    }

    /**
     * @return ScopedCache<string>
     */
    private function openCache(): ScopedCache
    {
        return new ScopedCache(new FileCache($this->cacheDir));
    }

    private function countCacheFiles(): int
    {
        return count(glob($this->cacheDir . '/*.php') ?: []);
    }

    /**
     * The graph's path, read off the object.
     *
     * Private because nothing outside needs it, and pinned here because the
     * difference between a correct and an incorrect one is invisible in
     * behaviour: both keep working, and both leave `/` untouched on a machine
     * that cannot write there.
     */
    private function graphPathOf(ScopedCache $scoped): ?string
    {
        $method = new ReflectionMethod($scoped, 'graphPath');

        /** @var ?string */
        return $method->invoke($scoped);
    }
}
