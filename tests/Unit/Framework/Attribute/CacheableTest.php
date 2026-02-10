<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;
use PHPUnit\Framework\TestCase;

final class CacheableTest extends TestCase
{
    protected function tearDown(): void
    {
        TestFacadeWithCache::clearMethodCache();
    }

    public function test_cacheable_attribute_can_be_instantiated(): void
    {
        $cacheable = new Cacheable(ttl: 3600);

        self::assertSame(3600, $cacheable->ttl);
        self::assertNull($cacheable->key);
    }

    public function test_cacheable_attribute_with_custom_key(): void
    {
        $cacheable = new Cacheable(ttl: 1800, key: 'custom-key');

        self::assertSame(1800, $cacheable->ttl);
        self::assertSame('custom-key', $cacheable->key);
    }

    public function test_cached_method_returns_cached_result(): void
    {
        $facade = new TestFacadeWithCache();

        $result1 = $facade->getExpensiveData();
        $result2 = $facade->getExpensiveData();

        // Should return the same result without recalculating
        self::assertSame($result1, $result2);

        // Call count should be 1, not 2, proving it's cached
        self::assertSame(1, $facade->getCallCount());
    }

    public function test_cached_method_respects_different_arguments(): void
    {
        $facade = new TestFacadeWithCache();

        $result1 = $facade->getDataWithArgs(1);
        $result2 = $facade->getDataWithArgs(2);
        $result3 = $facade->getDataWithArgs(1); // Should use cache

        self::assertNotSame($result1, $result2);
        self::assertSame($result1, $result3);

        // Should be called 2 times (once for each unique argument)
        self::assertSame(2, $facade->getArgsCallCount());
    }

    public function test_clear_method_cache_removes_all_cached_results(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getExpensiveData();
        self::assertSame(1, $facade->getCallCount());

        TestFacadeWithCache::clearMethodCache();

        $facade->getExpensiveData();
        // Should be called again after clearing cache
        self::assertSame(2, $facade->getCallCount());
    }

    public function test_clear_method_cache_for_specific_method(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getExpensiveData();
        $facade->getDataWithArgs(1);

        TestFacadeWithCache::clearMethodCacheFor('getExpensiveData');

        $facade->getExpensiveData();
        $facade->getDataWithArgs(1); // Should still be cached

        self::assertSame(2, $facade->getCallCount());
        self::assertSame(1, $facade->getArgsCallCount());
    }

    public function test_non_cacheable_method_is_not_cached(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getNonCachedData();
        $facade->getNonCachedData();

        // Should be called twice (not cached)
        self::assertSame(2, $facade->getNonCachedCallCount());
    }

    public function test_cache_expires_after_ttl(): void
    {
        $facade = new TestFacadeWithCacheShortTTL();

        $result1 = $facade->getDataWithShortTTL();
        self::assertSame(1, $facade->getCallCount());

        // Wait for cache to expire (short TTL of 1 second)
        sleep(2);

        $result2 = $facade->getDataWithShortTTL();

        // Should be called again after expiration
        self::assertSame(2, $facade->getCallCount());
        self::assertNotSame($result1, $result2);
    }
}

/**
 * Test facade that uses CacheableTrait.
 */
final class TestFacadeWithCache
{
    use CacheableTrait;

    private int $callCount = 0;

    private int $argsCallCount = 0;

    private int $nonCachedCallCount = 0;

    #[Cacheable(ttl: 3600)]
    public function getExpensiveData(): string
    {
        return $this->cached(__METHOD__, [], function (): string {
            ++$this->callCount;
            return 'expensive-result-' . $this->callCount;
        });
    }

    #[Cacheable(ttl: 3600)]
    public function getDataWithArgs(int $id): string
    {
        return $this->cached(__METHOD__, [$id], function () use ($id): string {
            ++$this->argsCallCount;
            return "result-{$id}-{$this->argsCallCount}";
        });
    }

    public function getNonCachedData(): string
    {
        ++$this->nonCachedCallCount;
        return 'non-cached-result-' . $this->nonCachedCallCount;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function getArgsCallCount(): int
    {
        return $this->argsCallCount;
    }

    public function getNonCachedCallCount(): int
    {
        return $this->nonCachedCallCount;
    }
}

/**
 * Test facade with short TTL for testing expiration.
 */
final class TestFacadeWithCacheShortTTL
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 1)] // 1 second TTL
    public function getDataWithShortTTL(): string
    {
        return $this->cached(__METHOD__, [], function (): string {
            ++$this->callCount;
            return 'result-' . time();
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}
