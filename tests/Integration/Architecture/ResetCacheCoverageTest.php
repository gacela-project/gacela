<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use SplFileInfo;

use function array_diff;
use function array_keys;
use function array_slice;
use function array_unique;
use function dirname;
use function file_get_contents;
use function implode;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_ends_with;

/**
 * Guards the wiring of `Gacela::resetCache()`.
 *
 * The defect this exists for is not a broken reset — it is a working reset that
 * nothing calls. `ClassValidator::resetCache()` and `ReflectionClassPool::reset()`
 * were both implemented, both unit-tested, and both reachable only from the test
 * written to exercise them. Production never called either, so a memoized
 * `class_exists()` answer survived `Gacela::resetCache()` indefinitely.
 *
 * A class holding process-global state therefore has to be reset centrally, or
 * be listed below with a reason. The allow-list is self-invalidating: an entry
 * that no longer describes reality fails just as loudly as a missing reset,
 * because an allowance that outlives its reason is a mute button.
 */
final class ResetCacheCoverageTest extends TestCase
{
    /**
     * Classes that hold static state on purpose and must NOT be cleared by
     * `Gacela::resetCache()`. Configuration lifetime is not cache lifetime.
     *
     * @var array<string,string>
     */
    private const array RESET_EXEMPT = [
        'Gacela' => 'is the central reset itself',
        'CacheableConfig' => 'holds application configuration: the cache backend registered via CacheableConfig::setStorage(). Nothing in the framework sets it, so clearing it here would silently drop the app\'s configured storage',
        'HealthCheckRegistry' => 'holds checks registered through GacelaConfig::addHealthCheck(). Configuration, re-established by bootstrap, which resets it explicitly at Gacela::bootstrap()',
        'ReflectionClassPool' => 'pure memoization keyed by class name. Reflection of a loaded class cannot change, so nothing goes stale, and the pool is bounded by the set of loaded classes, so it is not a leak. reset() exists for test isolation',
    ];

    public function test_every_resettable_class_is_reached_by_reset_cache(): void
    {
        $unreached = array_diff(
            $this->classesDeclaringAReset(),
            $this->classesReachedByResetCache(),
            array_keys(self::RESET_EXEMPT),
        );

        self::assertSame([], array_values($unreached), sprintf(
            "These classes declare a reset that Gacela::resetCache() never reaches: %s.\n"
            . 'Either call it from Gacela::resetCache(), or add it to RESET_EXEMPT with the reason '
            . 'its state must outlive a cache reset.',
            implode(', ', $unreached),
        ));
    }

    /**
     * The scanner above only walks `src/`, so the container's own process-global
     * reflection caches are invisible to it -- and they are the largest pile of
     * memoized class facts in a Gacela process: property plans, `#[Lazy]`,
     * `#[Singleton]`/`#[Factory]`, instantiability, `declares __invoke`.
     *
     * The container documents "a worker that re-bootstraps" as exactly the case
     * for clearing them, which is what `Gacela::resetCache()` is. Leaving them
     * behind is the same defect this whole test exists for -- a working reset
     * nothing calls -- with the reset living in a dependency.
     */
    public function test_the_containers_process_global_caches_are_reached_by_reset_cache(): void
    {
        self::assertContains(
            'GacelaContainer',
            $this->classesReachedByResetCache(),
            'Gacela::resetCache() must call Gacela\Container\Container::resetStaticCaches(): '
            . 'the container memoizes class facts for the whole process, and a Gacela reset is a re-bootstrap.',
        );
    }

    /**
     * An exemption for a class that no longer declares a reset, or that is now
     * reset centrally after all, is stale — and a stale exemption is how the
     * next leak gets waved through.
     */
    public function test_no_exemption_outlives_its_reason(): void
    {
        $declaring = $this->classesDeclaringAReset();
        $reached = $this->classesReachedByResetCache();

        foreach (self::RESET_EXEMPT as $class => $reason) {
            self::assertContains($class, $declaring, sprintf(
                '%s is exempted ("%s") but no longer declares a reset — drop the exemption.',
                $class,
                $reason,
            ));

            if ($class === 'Gacela') {
                continue;
            }

            self::assertNotContains($class, $reached, sprintf(
                '%s is exempted ("%s") but Gacela::resetCache() now reaches it — drop the exemption.',
                $class,
                $reason,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function classesDeclaringAReset(): array
    {
        $root = dirname(__DIR__, 3) . '/src/Framework';
        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        $found = [];
        foreach ($files as $path => $file) {
            if (!$file->isFile() || !str_ends_with((string)$path, '.php')) {
                continue;
            }

            $contents = (string)file_get_contents((string)$path);
            if (preg_match('/public static function reset[A-Za-z]*\(/', $contents) === 1) {
                $found[] = $file->getBasename('.php');
            }
        }

        sort($found);

        return array_values(array_unique($found));
    }

    /**
     * Classes whose reset is invoked by `Gacela::resetCache()`, plus those
     * invoked by the resets it calls — `Config::resetInstance()` is what
     * actually clears `EventDispatcherProvider`.
     *
     * @return list<string>
     */
    private function classesReachedByResetCache(): array
    {
        $direct = $this->resetCallsIn($this->sourceOfResetCache());

        $reached = $direct;
        foreach ($direct as $class) {
            $file = $this->fileForClass($class);
            if ($file !== null) {
                $reached = [...$reached, ...$this->resetCallsIn((string)file_get_contents($file))];
            }
        }

        sort($reached);

        return array_values(array_unique($reached));
    }

    private function sourceOfResetCache(): string
    {
        $method = new ReflectionMethod(Gacela::class, 'resetCache');
        $lines = file((string)$method->getFileName()) ?: [];

        return implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));
    }

    /**
     * @return list<string>
     */
    private function resetCallsIn(string $source): array
    {
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)::reset[A-Za-z]*\(/', $source, $matches);

        /** @var list<string> $classes */
        $classes = $matches[1];

        return $classes;
    }

    private function fileForClass(string $shortName): ?string
    {
        $root = dirname(__DIR__, 3) . '/src/Framework';
        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $path => $file) {
            if ($file->isFile() && $file->getBasename('.php') === $shortName && str_ends_with((string)$path, '.php')) {
                return (string)$path;
            }
        }

        return null;
    }
}
