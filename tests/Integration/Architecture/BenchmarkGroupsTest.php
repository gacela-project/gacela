<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PhpBench\Attributes\Groups;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function array_intersect;
use function array_values;
use function count;
use function dirname;
use function implode;
use function sprintf;
use function str_replace;

/**
 * Guards the benchmark group taxonomy that decides what CI actually measures.
 *
 * `.github/workflows/phpbench.yml` runs exactly two bench steps: `--group=gate`
 * (blocking) and `--group=informational` (reporting). A subject in neither runs
 * in neither step — it is dead weight that looks like coverage. `CacheableBench`
 * sat in that hole, carrying only its domain group `cacheable`, and was only
 * ever executed as a side effect of the baseline run benchmarking the whole
 * suite. Once the baseline was narrowed to match the head's group, it would
 * have stopped running anywhere, silently.
 *
 * A subject in *both* is the opposite error: the same numbers would block a
 * merge and be reported as non-blocking.
 */
final class BenchmarkGroupsTest extends TestCase
{
    private const array GATING_GROUPS = ['gate', 'informational'];

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function benchClassProvider(): iterable
    {
        $root = dirname(__DIR__, 2) . '/Benchmark';

        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $path => $file) {
            if (!$file->isFile() || !str_ends_with((string)$path, 'Bench.php')) {
                continue;
            }

            $relative = str_replace([$root, '/', '.php'], ['', '\\', ''], (string)$path);
            /** @var class-string $class */
            $class = 'GacelaTest\Benchmark' . $relative;

            yield $class => [$class];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('benchClassProvider')]
    public function test_bench_class_declares_exactly_one_gating_group(string $class): void
    {
        $attributes = (new ReflectionClass($class))->getAttributes(Groups::class);

        self::assertCount(1, $attributes, sprintf(
            '%s has no #[Groups] — it would run in neither CI bench step.',
            $class,
        ));

        /** @var list<string> $groups */
        $groups = $attributes[0]->getArguments()[0];
        $gating = array_values(array_intersect($groups, self::GATING_GROUPS));

        self::assertCount(1, $gating, sprintf(
            "%s declares [%s]; expected exactly one of [%s].\n"
            . 'CI runs --group=gate (blocking) and --group=informational (reporting); '
            . 'neither means unmeasured, both means measured twice with conflicting authority.',
            $class,
            implode(', ', $groups),
            implode(', ', self::GATING_GROUPS),
        ));
    }

    public function test_the_suite_has_benches_to_check(): void
    {
        self::assertGreaterThan(10, count([...self::benchClassProvider()]));
    }
}
