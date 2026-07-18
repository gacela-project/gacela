<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\CycleDetectedException;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

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
        DirectoryUtil::removeDir($this->cacheDir);
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

    public function test_clear_drops_values_and_graph(): void
    {
        $this->cache->put('ns:X', 'env-x');
        $this->cache->put('file:a.php', 'a');
        $this->cache->dependsOn('file:a.php', 'ns:X');

        $this->cache->clear();

        self::assertFalse($this->cache->has('ns:X'));
        self::assertFalse($this->cache->has('file:a.php'));
        self::assertSame([], $this->cache->dependents('ns:X'));
    }

    public function test_clear_graph_is_gone_after_restart(): void
    {
        $this->cache->dependsOn('file:a.php', 'ns:X');
        $this->cache->clear();

        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        self::assertSame([], $reopened->dependents('ns:X'));
    }

    public function test_put_honors_ttl_passthrough(): void
    {
        $this->cache->put('short', 'lived', ttl: -1);

        self::assertNull($this->cache->get('short'));
        self::assertFalse($this->cache->has('short'));
    }

    public function test_invalidate_middle_of_chain_leaves_ancestors_alone(): void
    {
        // `a` depends on `b` depends on `c`. Invalidating `b` drops `b` and
        // its dependent `a`, but leaves `c` (which `b` depends on) intact.
        $this->cache->put('a', 'A');
        $this->cache->put('b', 'B');
        $this->cache->put('c', 'C');
        $this->cache->dependsOn('a', 'b');
        $this->cache->dependsOn('b', 'c');

        $this->cache->invalidate('b');

        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
        self::assertSame('C', $this->cache->get('c'));
    }

    public function test_diamond_dependency_cascades_each_node_once(): void
    {
        // `a` depends on both `b` and `c`; both depend on `d`. Invalidating
        // `d` must drop every node — and exercise the BFS `$seen` dedup,
        // since `a` is otherwise reachable twice (via `b` and via `c`).
        $this->cache->put('a', 'A');
        $this->cache->put('b', 'B');
        $this->cache->put('c', 'C');
        $this->cache->put('d', 'D');

        $this->cache->dependsOn('a', 'b');
        $this->cache->dependsOn('a', 'c');
        $this->cache->dependsOn('b', 'd');
        $this->cache->dependsOn('c', 'd');

        $this->cache->invalidate('d');

        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
        self::assertFalse($this->cache->has('c'));
        self::assertFalse($this->cache->has('d'));
    }

    public function test_deep_chain_cascades_fully(): void
    {
        $depth = 20;
        for ($i = 0; $i <= $depth; ++$i) {
            $this->cache->put('n_' . $i, $i);
        }

        for ($i = 0; $i < $depth; ++$i) {
            $this->cache->dependsOn('n_' . $i, 'n_' . ($i + 1));
        }

        $this->cache->invalidate('n_' . $depth);

        for ($i = 0; $i <= $depth; ++$i) {
            self::assertFalse($this->cache->has('n_' . $i));
        }
    }

    public function test_partial_invalidate_keeps_siblings_and_parent(): void
    {
        $this->cache->put('ns:X', 'env');
        $this->cache->put('file:a', 'a');
        $this->cache->put('file:b', 'b');
        $this->cache->dependsOn('file:a', 'ns:X');
        $this->cache->dependsOn('file:b', 'ns:X');

        $this->cache->invalidate('file:a');

        self::assertFalse($this->cache->has('file:a'));
        self::assertSame('b', $this->cache->get('file:b'));
        self::assertSame('env', $this->cache->get('ns:X'));
        self::assertSame(['file:b'], $this->cache->dependents('ns:X'));

        // Parent still cascades to the remaining sibling.
        $this->cache->invalidate('ns:X');
        self::assertFalse($this->cache->has('file:b'));
    }

    public function test_dependson_declared_before_put_still_invalidates(): void
    {
        // Edges may be declared before the values exist.
        $this->cache->dependsOn('file:a', 'ns:X');

        $this->cache->put('ns:X', 'env');
        $this->cache->put('file:a', 'a');

        $this->cache->invalidate('ns:X');

        self::assertFalse($this->cache->has('file:a'));
    }

    public function test_put_on_existing_key_preserves_edges(): void
    {
        $this->cache->put('ns:X', 'env');
        $this->cache->put('file:a', 'first');
        $this->cache->dependsOn('file:a', 'ns:X');

        $this->cache->put('file:a', 'second');

        self::assertSame(['file:a'], $this->cache->dependents('ns:X'));

        $this->cache->invalidate('ns:X');
        self::assertFalse($this->cache->has('file:a'));
    }

    public function test_invalidate_persists_pruned_graph_to_disk(): void
    {
        $this->cache->dependsOn('file:a', 'ns:X');

        $this->cache->invalidate('ns:X');

        $reopened = new ScopedCache(new FileCache($this->cacheDir));
        self::assertSame([], $reopened->dependents('ns:X'));
    }

    public function test_invalidate_leaf_persists_pruned_graph_to_disk(): void
    {
        $this->cache->dependsOn('file:a', 'ns:X');

        $this->cache->invalidateLeaf('ns:X');

        $reopened = new ScopedCache(new FileCache($this->cacheDir));
        self::assertSame([], $reopened->dependents('ns:X'));
    }

    public function test_duplicate_edge_does_not_rewrite_graph_file(): void
    {
        $this->cache->dependsOn('file:a', 'ns:X');

        $graphFile = $this->cacheDir . '/.gacela-scoped-cache-graph.php';
        self::assertFileExists($graphFile);
        unlink($graphFile);

        // Re-declaring an existing edge must not persist the graph again.
        $this->cache->dependsOn('file:a', 'ns:X');

        self::assertFileDoesNotExist($graphFile);
    }

    public function test_cascade_continues_past_already_seen_dependents(): void
    {
        // dependents('x') lists an already-visited node (`a`) before a fresh
        // one (`b`); the BFS must skip `a` and still reach `b`.
        $this->cache->put('b', 'B');

        $this->cache->dependsOn('a', 'k');
        $this->cache->dependsOn('x', 'k');
        $this->cache->dependsOn('a', 'x');
        $this->cache->dependsOn('b', 'x');

        $this->cache->invalidate('k');

        self::assertFalse($this->cache->has('b'));
    }

    public function test_cycle_check_continues_past_seen_parents_in_crafted_graph(): void
    {
        // A hand-written graph file may contain cycles the API would have
        // rejected. Reaching `t` from `s` requires walking past the
        // already-seen `s` inside `m`'s parent list.
        file_put_contents(
            $this->cacheDir . '/.gacela-scoped-cache-graph.php',
            "<?php return ['s' => ['m'], 'm' => ['s', 't']];",
        );
        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        $this->expectException(CycleDetectedException::class);
        $reopened->dependsOn('t', 's');
    }

    public function test_loadgraph_keeps_valid_parent_listed_after_malformed_one(): void
    {
        file_put_contents(
            $this->cacheDir . '/.gacela-scoped-cache-graph.php',
            "<?php return ['file:d' => [789, 'ns:Z']];",
        );

        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        self::assertSame(['file:d'], $reopened->dependents('ns:Z'));
    }

    public function test_loadgraph_ignores_non_array_payload(): void
    {
        file_put_contents(
            $this->cacheDir . '/.gacela-scoped-cache-graph.php',
            "<?php\n\nreturn 'not-a-graph';\n",
        );

        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        self::assertSame([], $reopened->dependents('ns:X'));
        $reopened->put('ns:X', 'env');
        self::assertSame('env', $reopened->get('ns:X'));
    }

    public function test_loadgraph_skips_malformed_entries_keeps_valid_ones(): void
    {
        file_put_contents(
            $this->cacheDir . '/.gacela-scoped-cache-graph.php',
            <<<'PHP'
                <?php

                return [
                    'file:a' => ['ns:X'],
                    123      => ['bad-int-key'],
                    'file:b' => 'not-an-array',
                    'file:c' => ['ns:Y', 456],
                ];
                PHP,
        );

        $reopened = new ScopedCache(new FileCache($this->cacheDir));

        self::assertSame(['file:a'], $reopened->dependents('ns:X'));
        self::assertSame(['file:c'], $reopened->dependents('ns:Y'));
        self::assertSame([], $reopened->dependents('bad-int-key'));
        self::assertSame([], $reopened->dependents('file:b'));
    }
}
