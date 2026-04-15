<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ScopedCache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

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
}
