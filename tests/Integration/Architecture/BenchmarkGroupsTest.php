<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PhpBench\Attributes\Groups;
use PhpBench\Attributes\RetryThreshold;
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
use function in_array;
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

    /** Kept in one place so the benches and this guard cannot disagree. */
    private const int INFORMATIONAL_RETRY_THRESHOLD = 20;

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function benchClassProvider(): iterable
    {
        $root = dirname(__DIR__, 2) . '/Benchmark';

        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $path => $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!str_ends_with((string)$path, 'Bench.php')) {
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

    /**
     * An `informational` class must loosen its retry threshold; a `gate` class
     * must not.
     *
     * `phpbench.json` sets `runner.retry_threshold: 5` for the whole suite, and
     * a retry is **unbounded** — phpbench 1.7 has `setRetryLimit()` and calls it
     * from nowhere, so there is no cap and no config key for one. A group whose
     * numbers are never asserted therefore pays an open-ended amount of CI time
     * to stabilise a reading nothing reads: measured on `ScopedCacheBench`, one
     * machine, one commit, 34s at threshold 5 against 6.3s at 10. That is what
     * made the guard's baseline step run 11–12 minutes and get killed by the job
     * timeout while the gate step beside it passed in 15 seconds.
     *
     * The gate group keeps the strict 5, because a ±10% assertion means nothing
     * on top of an unstable reading. See tests/Benchmark/README.md.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('benchClassProvider')]
    public function test_only_the_informational_benches_loosen_the_retry_threshold(string $class): void
    {
        $reflection = new ReflectionClass($class);

        /** @var list<string> $groups */
        $groups = $reflection->getAttributes(Groups::class)[0]->getArguments()[0];
        $isInformational = in_array('informational', $groups, true);
        $retry = $reflection->getAttributes(RetryThreshold::class);

        if (!$isInformational) {
            self::assertSame([], $retry, sprintf(
                '%s is gated and declares #[RetryThreshold]. A gated subject keeps the strict '
                . 'phpbench.json threshold: the ±10%% assertion is only meaningful on a stable reading.',
                $class,
            ));

            return;
        }

        self::assertCount(1, $retry, sprintf(
            '%s is informational and declares no #[RetryThreshold]. Retries are unbounded, so a '
            . 'reading nothing asserts on can stall CI until the job timeout. Add #[RetryThreshold(%d)].',
            $class,
            self::INFORMATIONAL_RETRY_THRESHOLD,
        ));

        self::assertSame(
            self::INFORMATIONAL_RETRY_THRESHOLD,
            $retry[0]->getArguments()[0],
            sprintf('%s should use the same threshold as every other informational bench.', $class),
        );
    }

    public function test_the_suite_has_benches_to_check(): void
    {
        self::assertGreaterThan(10, count([...self::benchClassProvider()]));
    }
}
