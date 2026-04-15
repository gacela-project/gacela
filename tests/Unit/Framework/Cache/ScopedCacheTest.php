<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\CycleDetectedException;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use PHPUnit\Framework\TestCase;

use function glob;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ScopedCacheTest extends TestCase
{
    private string $cacheDir;

    /** @var ScopedCache<mixed> */
    private ScopedCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-scoped-cache-test-' . uniqid('', true);
        $this->cache = new ScopedCache(new FileCache($this->cacheDir));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function test_put_and_get_round_trip(): void
    {
        $this->cache->put('file:a.php', 'compiled-a');

        self::assertTrue($this->cache->has('file:a.php'));
        self::assertSame('compiled-a', $this->cache->get('file:a.php'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        self::assertNull($this->cache->get('missing'));
        self::assertFalse($this->cache->has('missing'));
    }

    public function test_dependents_is_empty_when_no_edges_declared(): void
    {
        $this->cache->put('ns:X', 'env-x');

        self::assertSame([], $this->cache->dependents('ns:X'));
    }

    public function test_dependents_reports_direct_children_only(): void
    {
        $this->cache->dependsOn('file:a.php', 'ns:X');
        $this->cache->dependsOn('file:b.php', 'ns:X');
        $this->cache->dependsOn('ns:X', 'ns:root');

        self::assertSame(['file:a.php', 'file:b.php'], $this->cache->dependents('ns:X'));
        self::assertSame(['ns:X'], $this->cache->dependents('ns:root'));
    }

    public function test_invalidate_cascades_through_transitive_dependents(): void
    {
        $this->cache->put('ns:X', 'env');
        $this->cache->put('file:a.php', 'a');
        $this->cache->put('file:b.php', 'b');
        $this->cache->put('fragment:a1', 'frag');

        $this->cache->dependsOn('file:a.php', 'ns:X');
        $this->cache->dependsOn('file:b.php', 'ns:X');
        $this->cache->dependsOn('fragment:a1', 'file:a.php');

        $this->cache->invalidate('ns:X');

        self::assertFalse($this->cache->has('ns:X'));
        self::assertFalse($this->cache->has('file:a.php'));
        self::assertFalse($this->cache->has('file:b.php'));
        self::assertFalse($this->cache->has('fragment:a1'));
    }

    public function test_invalidate_leaves_unrelated_entries_alone(): void
    {
        $this->cache->put('ns:X', 'env-x');
        $this->cache->put('ns:Y', 'env-y');
        $this->cache->put('file:a.php', 'a');

        $this->cache->dependsOn('file:a.php', 'ns:X');

        $this->cache->invalidate('ns:X');

        self::assertFalse($this->cache->has('ns:X'));
        self::assertFalse($this->cache->has('file:a.php'));
        self::assertSame('env-y', $this->cache->get('ns:Y'));
    }

    public function test_invalidate_leaf_does_not_cascade_to_dependents(): void
    {
        $this->cache->put('ns:X', 'env-x');
        $this->cache->put('file:a.php', 'a');

        $this->cache->dependsOn('file:a.php', 'ns:X');

        $this->cache->invalidateLeaf('ns:X');

        self::assertFalse($this->cache->has('ns:X'));
        self::assertSame('a', $this->cache->get('file:a.php'));
    }

    public function test_invalidate_leaf_drops_edges_referencing_the_key(): void
    {
        $this->cache->put('ns:X', 'env-x');
        $this->cache->put('file:a.php', 'a');

        $this->cache->dependsOn('file:a.php', 'ns:X');
        $this->cache->invalidateLeaf('ns:X');

        self::assertSame([], $this->cache->dependents('ns:X'));
    }

    public function test_dependency_graph_survives_process_restart(): void
    {
        $this->cache->put('ns:X', 'env-x');
        $this->cache->put('file:a.php', 'a');
        $this->cache->dependsOn('file:a.php', 'ns:X');

        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        self::assertSame(['file:a.php'], $reopened->dependents('ns:X'));

        $reopened->invalidate('ns:X');
        self::assertFalse($reopened->has('file:a.php'));
    }

    public function test_dependency_survives_restart_with_leaf_invalidation(): void
    {
        // Acceptance case: `file:X` depending on `ns:Y` survives process
        // restart and invalidates correctly.
        $this->cache->put('ns:Y', 'env-y');
        $this->cache->put('file:X', 'compiled-x');
        $this->cache->dependsOn('file:X', 'ns:Y');

        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        $reopened->invalidateLeaf('file:X');
        self::assertFalse($reopened->has('file:X'));
        self::assertSame('env-y', $reopened->get('ns:Y'));

        $reopened->invalidate('ns:Y');
        self::assertFalse($reopened->has('ns:Y'));
    }

    public function test_dependson_rejects_self_dependency(): void
    {
        $this->expectException(CycleDetectedException::class);

        $this->cache->dependsOn('same', 'same');
    }

    public function test_dependson_rejects_two_node_cycle(): void
    {
        $this->cache->dependsOn('a', 'b');

        $this->expectException(CycleDetectedException::class);
        $this->cache->dependsOn('b', 'a');
    }

    public function test_dependson_rejects_transitive_cycle(): void
    {
        $this->cache->dependsOn('a', 'b');
        $this->cache->dependsOn('b', 'c');

        $this->expectException(CycleDetectedException::class);
        $this->cache->dependsOn('c', 'a');
    }

    public function test_dependson_rejected_cycle_leaves_graph_unchanged(): void
    {
        $this->cache->dependsOn('a', 'b');

        try {
            $this->cache->dependsOn('b', 'a');
            self::fail('expected CycleDetectedException');
        } catch (CycleDetectedException) {
            // expected
        }

        self::assertSame(['a'], $this->cache->dependents('b'));
        self::assertSame([], $this->cache->dependents('a'));
    }

    public function test_dependson_is_idempotent(): void
    {
        $this->cache->dependsOn('file:a.php', 'ns:X');
        $this->cache->dependsOn('file:a.php', 'ns:X');

        self::assertSame(['file:a.php'], $this->cache->dependents('ns:X'));
    }

    public function test_multiple_parents_all_cascade(): void
    {
        $this->cache->put('file:a.php', 'a');
        $this->cache->put('ns:X', 'x');
        $this->cache->put('ns:Y', 'y');

        $this->cache->dependsOn('file:a.php', 'ns:X');
        $this->cache->dependsOn('file:a.php', 'ns:Y');

        $this->cache->invalidate('ns:X');

        self::assertFalse($this->cache->has('file:a.php'));
        self::assertFalse($this->cache->has('ns:X'));
        self::assertSame('y', $this->cache->get('ns:Y'));
    }

    public function test_invalidate_missing_key_is_noop(): void
    {
        $this->cache->invalidate('never-existed');

        self::assertFalse($this->cache->has('never-existed'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->removeDir($entry);
            } else {
                unlink($entry);
            }
        }

        foreach (glob($dir . '/.[!.]*') ?: [] as $dotfile) {
            if (is_dir($dotfile)) {
                $this->removeDir($dotfile);
            } else {
                unlink($dotfile);
            }
        }

        rmdir($dir);
    }
}
