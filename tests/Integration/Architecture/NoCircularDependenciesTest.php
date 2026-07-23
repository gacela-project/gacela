<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_keys;
use function count;
use function file_get_contents;
use function implode;
use function preg_match;
use function preg_match_all;
use function sort;
use function sprintf;

/**
 * Guards the source tree against circular dependencies between classes.
 *
 * The graph is built from fully-qualified `use Gacela\{Framework,Console,PHPStan}\...`
 * imports (short-name matching would conflate the framework `Container` with the vendor
 * `Gacela\Container\Container`), then Tarjan's algorithm reports every strongly connected
 * component larger than one node.
 *
 * Two benign cycles are allow-listed: cohesive aggregate/helper clusters where the
 * back-edge is a constructor dependency or a parameter type, and where inverting the
 * dependency would add indirection without decoupling anything real. Any *new* cycle
 * fails the test.
 */
final class NoCircularDependenciesTest extends TestCase
{
    private const SRC = __DIR__ . '/../../../src';

    /**
     * Each allow-listed cycle is the sorted list of its member classes joined by ' | '.
     *
     * @var list<string>
     */
    private const ALLOWED_CYCLES = [
        'Gacela\Framework\AbstractProvider | Gacela\Framework\Attribute\ProvidesScanner',
        'Gacela\Framework\Bootstrap\SetupGacela | Gacela\Framework\Bootstrap\Setup\PropertyMerger'
            . ' | Gacela\Framework\Bootstrap\Setup\SetupInitializer | Gacela\Framework\Bootstrap\Setup\SetupMerger',
    ];

    public function test_source_tree_has_no_unexpected_circular_dependencies(): void
    {
        $edges = $this->buildDependencyGraph();

        $cycles = [];
        foreach ($this->stronglyConnectedComponents($edges) as $component) {
            if (count($component) < 2) {
                continue;
            }
            sort($component);
            $cycles[] = implode(' | ', $component);
        }
        sort($cycles);

        $unexpected = array_values(array_diff($cycles, self::ALLOWED_CYCLES));

        self::assertSame(
            [],
            $unexpected,
            sprintf(
                "Unexpected dependency cycle(s) introduced:\n- %s\n\nBreak the cycle, or (if genuinely benign) add it to ALLOWED_CYCLES with a rationale.",
                implode("\n- ", $unexpected),
            ),
        );
    }

    public function test_allow_listed_cycles_still_exist(): void
    {
        $edges = $this->buildDependencyGraph();

        $found = [];
        foreach ($this->stronglyConnectedComponents($edges) as $component) {
            if (count($component) < 2) {
                continue;
            }
            sort($component);
            $found[] = implode(' | ', $component);
        }

        foreach (self::ALLOWED_CYCLES as $allowed) {
            self::assertContains(
                $allowed,
                $found,
                sprintf('Allow-listed cycle no longer exists; remove it from ALLOWED_CYCLES: %s', $allowed),
            );
        }
    }

    /**
     * @return array<string, list<string>> adjacency list of intra-source class dependencies
     */
    private function buildDependencyGraph(): array
    {
        /** @var array<string, list<string>> $rawEdges */
        $rawEdges = [];
        $defined = [];

        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::SRC, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            if (preg_match('/^namespace\s+([^;]+);/m', $code, $ns) !== 1) {
                continue;
            }
            if (preg_match('/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $code, $type) !== 1) {
                continue;
            }

            $fqn = trim($ns[1]) . '\\' . trim($type[1]);
            $defined[$fqn] = true;

            preg_match_all('/^use\s+(Gacela\\\\(?:Framework|Console|PHPStan)\\\\[^;\s]+?)(?:\s+as\s+\w+)?;/m', $code, $uses);
            /** @var list<string> $imports */
            $imports = $uses[1];
            $rawEdges[$fqn] = $imports;
        }

        $edges = [];
        foreach ($rawEdges as $from => $tos) {
            $edges[$from] = array_values(array_filter(
                $tos,
                static fn (string $to): bool => isset($defined[$to]) && $to !== $from,
            ));
        }

        return $edges;
    }

    /**
     * Tarjan's strongly-connected-components algorithm (iterative).
     *
     * @param array<string, list<string>> $edges
     *
     * @return list<list<string>>
     */
    private function stronglyConnectedComponents(array $edges): array
    {
        $index = [];
        $low = [];
        $onStack = [];
        $stack = [];
        $result = [];
        $counter = 0;

        /** @var array<string, int> $nodes */
        $nodes = array_fill_keys(array_keys($edges), 0);

        foreach (array_keys($nodes) as $start) {
            if (isset($index[$start])) {
                continue;
            }

            // Iterative DFS: each frame tracks the node and how far through its edges we are.
            $work = [[$start, 0]];

            while ($work !== []) {
                [$node, $edgePointer] = $work[count($work) - 1];

                if ($edgePointer === 0) {
                    $index[$node] = $counter;
                    $low[$node] = $counter;
                    ++$counter;
                    $stack[] = $node;
                    $onStack[$node] = true;
                }

                $recursed = false;
                $neighbors = $edges[$node] ?? [];
                for ($i = $edgePointer; $i < count($neighbors); ++$i) {
                    $next = $neighbors[$i];
                    if (!isset($index[$next])) {
                        $work[count($work) - 1] = [$node, $i + 1];
                        $work[] = [$next, 0];
                        $recursed = true;
                        break;
                    }
                    if (($onStack[$next] ?? false) === true) {
                        $low[$node] = min($low[$node], $index[$next]);
                    }
                }

                if ($recursed) {
                    continue;
                }

                if ($low[$node] === $index[$node]) {
                    $component = [];
                    do {
                        $w = array_pop($stack);
                        $onStack[$w] = false;
                        $component[] = $w;
                    } while ($w !== $node);
                    $result[] = $component;
                }

                array_pop($work);
                if ($work !== []) {
                    $parent = $work[count($work) - 1][0];
                    $low[$parent] = min($low[$parent], $low[$node]);
                }
            }
        }

        return $result;
    }
}
