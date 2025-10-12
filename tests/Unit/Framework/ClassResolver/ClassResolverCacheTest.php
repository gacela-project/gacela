<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ClassResolverCacheTest extends TestCase
{
    protected function setUp(): void
    {
        ClassResolverCache::resetCache();
        InMemoryCache::resetCache();
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        ClassResolverCache::resetCache();
        InMemoryCache::resetCache();
        Config::resetInstance();
    }

    public function test_get_cache_returns_in_memory_cache_when_file_cache_is_disabled(): void
    {
        // Bootstrap with file cache disabled
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache = ClassResolverCache::getCache();

        self::assertInstanceOf(InMemoryCache::class, $cache);
    }

    public function test_get_cache_returns_same_instance_on_subsequent_calls(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache1 = ClassResolverCache::getCache();
        $cache2 = ClassResolverCache::getCache();

        // Should return the same cached instance
        self::assertSame($cache1, $cache2);
    }

    public function test_reset_cache_clears_cached_instance(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache1 = ClassResolverCache::getCache();

        ClassResolverCache::resetCache();

        $cache2 = ClassResolverCache::getCache();

        // After reset, should create a new instance (not the same object)
        self::assertNotSame($cache1, $cache2);
        self::assertInstanceOf(InMemoryCache::class, $cache2);
    }

    public function test_cache_can_store_and_retrieve_values(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache = ClassResolverCache::getCache();

        $cache->put('test-key', 'TestClassName');

        self::assertTrue($cache->has('test-key'));
        self::assertSame('TestClassName', $cache->get('test-key'));
    }

    public function test_cache_has_returns_false_for_non_existent_key(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache = ClassResolverCache::getCache();

        self::assertFalse($cache->has('non-existent-key'));
    }

    public function test_multiple_cache_operations(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache = ClassResolverCache::getCache();

        // Store multiple values
        $cache->put('key1', 'ClassName1');
        $cache->put('key2', 'ClassName2');
        $cache->put('key3', 'ClassName3');

        // Verify all values
        self::assertTrue($cache->has('key1'));
        self::assertTrue($cache->has('key2'));
        self::assertTrue($cache->has('key3'));
        self::assertSame('ClassName1', $cache->get('key1'));
        self::assertSame('ClassName2', $cache->get('key2'));
        self::assertSame('ClassName3', $cache->get('key3'));
    }

    public function test_cache_persists_across_multiple_get_cache_calls(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache1 = ClassResolverCache::getCache();
        $cache1->put('persistent-key', 'PersistentClass');

        // Get cache again (should be same instance)
        $cache2 = ClassResolverCache::getCache();

        // Value should still be there
        self::assertTrue($cache2->has('persistent-key'));
        self::assertSame('PersistentClass', $cache2->get('persistent-key'));
    }

    public function test_reset_cache_clears_stored_values(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $cache = ClassResolverCache::getCache();
        $cache->put('temp-key', 'TempClass');

        self::assertTrue($cache->has('temp-key'));

        // Reset should clear everything
        ClassResolverCache::resetCache();
        InMemoryCache::resetCache();

        $newCache = ClassResolverCache::getCache();

        // Old key should not exist in new cache
        self::assertFalse($newCache->has('temp-key'));
    }
}
