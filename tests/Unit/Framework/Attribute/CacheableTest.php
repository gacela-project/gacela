<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\Attribute\CacheStorageInterface;
use Gacela\Framework\Attribute\InMemoryCacheStorage;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class CacheableTest extends TestCase
{
    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_cacheable_attribute_can_be_instantiated(): void
    {
        $cacheable = new Cacheable(ttl: 3600);

        self::assertSame(3600, $cacheable->ttl);
        self::assertNull($cacheable->key);
    }

    public function test_cacheable_attribute_default_ttl_is_one_hour(): void
    {
        $cacheable = new Cacheable();

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

        self::assertSame($result1, $result2);
        self::assertSame(1, $facade->getCallCount());
    }

    public function test_cached_method_respects_different_arguments(): void
    {
        $facade = new TestFacadeWithCache();

        $result1 = $facade->getDataWithArgs(1);
        $result2 = $facade->getDataWithArgs(2);
        $result3 = $facade->getDataWithArgs(1);

        self::assertNotSame($result1, $result2);
        self::assertSame($result1, $result3);
        self::assertSame(2, $facade->getArgsCallCount());
    }

    public function test_clear_method_cache_removes_all_cached_results(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getExpensiveData();
        self::assertSame(1, $facade->getCallCount());

        TestFacadeWithCache::clearMethodCache();

        $facade->getExpensiveData();
        self::assertSame(2, $facade->getCallCount());
    }

    public function test_clear_method_cache_for_specific_method(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getExpensiveData();
        $facade->getDataWithArgs(1);

        TestFacadeWithCache::clearMethodCacheFor('getExpensiveData');

        $facade->getExpensiveData();
        $facade->getDataWithArgs(1);

        self::assertSame(2, $facade->getCallCount());
        self::assertSame(1, $facade->getArgsCallCount());
    }

    public function test_clear_method_cache_for_substring_does_not_affect_other_methods(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getExpensiveData();
        $facade->getDataWithArgs(1);

        TestFacadeWithCache::clearMethodCacheFor('get');

        $facade->getExpensiveData();
        $facade->getDataWithArgs(1);

        self::assertSame(1, $facade->getCallCount());
        self::assertSame(1, $facade->getArgsCallCount());
    }

    public function test_non_cacheable_method_is_not_cached(): void
    {
        $facade = new TestFacadeWithCache();

        $facade->getNonCachedData();
        $facade->getNonCachedData();

        self::assertSame(2, $facade->getNonCachedCallCount());
    }

    public function test_cache_expires_after_ttl(): void
    {
        $facade = new TestFacadeWithCacheShortTTL();

        $result1 = $facade->getDataWithShortTTL();
        self::assertSame(1, $facade->getCallCount());

        sleep(2);

        $result2 = $facade->getDataWithShortTTL();

        self::assertSame(2, $facade->getCallCount());
        self::assertNotSame($result1, $result2);
    }

    public function test_cached_method_is_accessible_from_subclass(): void
    {
        $child = new TestChildFacadeWithCache();

        $result1 = $child->getChildData();
        $result2 = $child->getChildData();

        self::assertSame($result1, $result2);
        self::assertSame(1, $child->getChildCallCount());
    }

    public function test_ttl_override_via_config_shortens_cache_lifetime(): void
    {
        CacheableConfig::setTtlOverrides([
            TestFacadeWithCache::class . '::getExpensiveData' => 1,
        ]);

        $facade = new TestFacadeWithCache();

        $facade->getExpensiveData();
        self::assertSame(1, $facade->getCallCount());

        sleep(2);

        $facade->getExpensiveData();
        self::assertSame(2, $facade->getCallCount());
    }

    public function test_custom_storage_backend_is_used(): void
    {
        $storage = new RecordingCacheStorage();
        CacheableConfig::setStorage($storage);

        $facade = new TestFacadeWithCache();
        $facade->getExpensiveData();

        self::assertCount(1, $storage->sets);
        self::assertSame(3600, $storage->sets[0]['ttl']);
    }

    public function test_key_template_interpolates_scalar_args(): void
    {
        $facade = new TestFacadeWithTemplatedKey();

        $facade->lookup(42);
        $facade->lookup(42);

        $storage = CacheableConfig::getStorage();
        self::assertTrue($storage->has('user:42'));
        self::assertSame(1, $facade->getCallCount());
    }

    public function test_bare_key_without_placeholders_is_shared_across_args(): void
    {
        $facade = new TestFacadeWithStaticKey();

        $first = $facade->findBy(1);
        $second = $facade->findBy(2);

        self::assertSame($first, $second);
        self::assertSame(1, $facade->getCallCount());
    }

    public function test_explicit_method_and_args_skip_backtrace_and_still_cache(): void
    {
        $facade = new TestFacadeWithExplicitCached();

        $first = $facade->load(7);
        $second = $facade->load(7);
        $third = $facade->load(8);

        self::assertSame($first, $second);
        self::assertNotSame($first, $third);
        self::assertSame(2, $facade->getCallCount());
    }

    public function test_explicit_method_works_when_cached_is_called_from_a_helper(): void
    {
        $facade = new TestFacadeWithHelperCached();

        $facade->compute();
        $facade->compute();

        self::assertSame(1, $facade->getCallCount());
    }

    public function test_cached_null_value_is_treated_as_a_hit(): void
    {
        $facade = new TestFacadeReturningNull();

        self::assertNull($facade->maybeFind());
        self::assertNull($facade->maybeFind());
        self::assertSame(1, $facade->getCallCount());
    }
}

final class TestFacadeWithCache
{
    use CacheableTrait;

    private int $callCount = 0;

    private int $argsCallCount = 0;

    private int $nonCachedCallCount = 0;

    #[Cacheable(ttl: 3600)]
    public function getExpensiveData(): string
    {
        return $this->cached(function (): string {
            ++$this->callCount;
            return 'expensive-result-' . $this->callCount;
        });
    }

    #[Cacheable(ttl: 3600)]
    public function getDataWithArgs(int $id): string
    {
        return $this->cached(function () use ($id): string {
            ++$this->argsCallCount;
            return sprintf('result-%d-%d', $id, $this->argsCallCount);
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

final class TestFacadeWithCacheShortTTL
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 1)]
    public function getDataWithShortTTL(): string
    {
        return $this->cached(function (): string {
            ++$this->callCount;
            return 'result-' . $this->callCount;
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

class TestParentFacadeWithCache
{
    use CacheableTrait;
}

final class TestChildFacadeWithCache extends TestParentFacadeWithCache
{
    private int $childCallCount = 0;

    #[Cacheable(ttl: 3600)]
    public function getChildData(): string
    {
        return $this->cached(function (): string {
            ++$this->childCallCount;
            return 'child-result-' . $this->childCallCount;
        });
    }

    public function getChildCallCount(): int
    {
        return $this->childCallCount;
    }
}

final class TestFacadeWithTemplatedKey
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600, key: 'user:{0}')]
    public function lookup(int $id): string
    {
        return $this->cached(function () use ($id): string {
            ++$this->callCount;
            return 'user-' . $id;
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class TestFacadeWithStaticKey
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600, key: 'shared')]
    public function findBy(int $id): string
    {
        return $this->cached(function () use ($id): string {
            ++$this->callCount;
            return 'item-' . $id;
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class TestFacadeWithExplicitCached
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600)]
    public function load(int $id): string
    {
        return $this->cached(function () use ($id): string {
            ++$this->callCount;
            return 'loaded-' . $id;
        }, __METHOD__, [$id]);
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class TestFacadeWithHelperCached
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600)]
    public function compute(): string
    {
        return $this->runCached('compute', []);
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    private function runCached(string $method, array $args): string
    {
        return $this->cached(function (): string {
            ++$this->callCount;
            return 'computed-' . $this->callCount;
        }, $method, $args);
    }
}

final class TestFacadeReturningNull
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600)]
    public function maybeFind(): ?string
    {
        return $this->cached(function (): ?string {
            ++$this->callCount;
            return null;
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class RecordingCacheStorage implements CacheStorageInterface
{
    /** @var list<array{key:string,value:mixed,ttl:int}> */
    public array $sets = [];

    private InMemoryCacheStorage $delegate;

    public function __construct()
    {
        $this->delegate = new InMemoryCacheStorage();
    }

    public function has(string $key): bool
    {
        return $this->delegate->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->delegate->get($key, $default);
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->sets[] = ['key' => $key, 'value' => $value, 'ttl' => $ttl];
        $this->delegate->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        $this->delegate->delete($key);
    }

    public function clear(): void
    {
        $this->delegate->clear();
    }

    public function deleteByPrefix(string $prefix): void
    {
        $this->delegate->deleteByPrefix($prefix);
    }
}
