<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

final class CachePerformanceTest extends TestCase
{
    private const ITERATIONS = 100;
    private const REVS = 100;

    public function setUp(): void
    {
        if (is_file(self::getGacelaCacheFileName())) {
            unlink(self::getGacelaCacheFileName());
        }
    }

    public function test_cache(): void
    {
        $durationCacheDisabled = $this->benchmarkWithCache(false);
        $durationCacheEnabled = $this->benchmarkWithCache(true);

        $message = sprintf(
            '$cacheDisabled:%d, $cacheEnabled:%d; enabling the cache should be faster!',
            $durationCacheDisabled,
            $durationCacheEnabled
        );

        self::assertThat($durationCacheEnabled, new LessThan($durationCacheDisabled), $message);
    }

    private static function getGacelaCacheFileName(): string
    {
        return __DIR__ . '/' . AbstractClassResolver::GACELA_CACHE_JSON_FILE;
    }

    private function benchmarkWithCache(bool $cachedEnabled): int
    {
        Util::gacelaBootstrapWithCache($cachedEnabled);

        $stopwatch = new Stopwatch();
        $stopwatch->start(__METHOD__);
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            for ($j = 0; $j < self::REVS; ++$j) {
                Util::loadGacelaCacheFiles();
            }
            Util::gacelaBootstrapWithCache($cachedEnabled);
        }
        $event = $stopwatch->stop(__METHOD__);

        return (int)$event->getDuration();
    }
}
