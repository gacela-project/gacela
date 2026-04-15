<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\Attribute\CacheStorageInterface;
use Gacela\Framework\Attribute\InMemoryCacheStorage;
use PHPUnit\Framework\TestCase;

use function count;
use function sprintf;
use function strlen;
use function strrpos;
use function substr;

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

    public function test_single_string_arg_is_hashed_directly(): void
    {
        $storage = new RecordingCacheStorage();
        CacheableConfig::setStorage($storage);

        $facade = new TestFacadeWithAdvancedArgs();
        $facade->byName('alice');

        self::assertSame(
            TestFacadeWithAdvancedArgs::class . '::byName::alice',
            $storage->sets[0]['key'],
        );
    }

    public function test_non_scalar_arg_falls_back_to_serialized_hash(): void
    {
        $storage = new RecordingCacheStorage();
        CacheableConfig::setStorage($storage);

        $facade = new TestFacadeWithAdvancedArgs();
        $facade->byArray(['a', 'b']);

        $key = $storage->sets[0]['key'];
        self::assertStringStartsWith(TestFacadeWithAdvancedArgs::class . '::byArray::', $key);
        self::assertSame(32, strlen(substr($key, strrpos($key, '::') + 2)));
    }

    public function test_multi_arg_cache_distinguishes_argument_combinations(): void
    {
        $facade = new TestFacadeWithAdvancedArgs();

        $facade->byNameAndAge('alice', 30);
        $facade->byNameAndAge('alice', 30);
        $facade->byNameAndAge('alice', 31);
        $facade->byNameAndAge('bob', 30);

        self::assertSame(3, $facade->getMultiArgCalls());
    }

    public function test_template_with_missing_index_renders_empty_string(): void
    {
        $facade = new TestFacadeWithTemplateEdgeCases();

        $facade->sparseKey(7);

        self::assertTrue(CacheableConfig::getStorage()->has('sparse:'));
    }

    public function test_template_with_non_scalar_arg_uses_serialized_hash(): void
    {
        $facade = new TestFacadeWithTemplateEdgeCases();

        $facade->objectKey(new ValueHolder('alpha'));
        $facade->objectKey(new ValueHolder('alpha'));
        $facade->objectKey(new ValueHolder('beta'));

        self::assertSame(2, $facade->getObjectKeyCalls());
    }

    public function test_explicit_method_accepts_plain_name_without_class_prefix(): void
    {
        $facade = new TestFacadeWithPlainExplicit();

        $facade->fetch();
        $facade->fetch();

        self::assertSame(1, $facade->getCallCount());
    }

    public function test_cache_is_shared_across_instances_of_same_class(): void
    {
        $first = new TestFacadeWithCache();
        $second = new TestFacadeWithCache();

        $first->getExpensiveData();
        $second->getExpensiveData();

        self::assertSame(1, $first->getCallCount());
        self::assertSame(0, $second->getCallCount());
    }

    public function test_repeated_calls_reuse_memoized_attribute(): void
    {
        $facade = new TestFacadeWithCache();

        for ($i = 0; $i < 5; ++$i) {
            $facade->getExpensiveData();
        }

        self::assertSame(1, $facade->getCallCount());
    }

    public function test_same_method_name_across_different_classes_uses_isolated_cache(): void
    {
        $first = new TestFacadeAlphaShared();
        $second = new TestFacadeBetaShared();

        $alphaResult = $first->compute(1);
        $betaResult = $second->compute(1);

        self::assertSame('alpha-1', $alphaResult);
        self::assertSame('beta-1', $betaResult);
        self::assertSame(1, $first->getCallCount());
        self::assertSame(1, $second->getCallCount());
    }

    public function test_cached_called_on_method_without_attribute_invokes_callback_every_time(): void
    {
        $facade = new TestFacadeWithCachedButNoAttribute();

        $facade->plain();
        $facade->plain();
        $facade->plain();

        self::assertSame(3, $facade->getCallCount());
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

final class TestFacadeWithAdvancedArgs
{
    use CacheableTrait;

    private int $multiArgCalls = 0;

    #[Cacheable(ttl: 3600)]
    public function byName(string $name): string
    {
        return $this->cached(static fn (): string => 'name-' . $name);
    }

    /**
     * @param list<string> $items
     */
    #[Cacheable(ttl: 3600)]
    public function byArray(array $items): int
    {
        return $this->cached(static fn (): int => count($items));
    }

    #[Cacheable(ttl: 3600)]
    public function byNameAndAge(string $name, int $age): string
    {
        return $this->cached(function () use ($name, $age): string {
            ++$this->multiArgCalls;
            return sprintf('%s-%d', $name, $age);
        });
    }

    public function getMultiArgCalls(): int
    {
        return $this->multiArgCalls;
    }
}

final class ValueHolder
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}

final class TestFacadeWithTemplateEdgeCases
{
    use CacheableTrait;

    private int $objectKeyCalls = 0;

    #[Cacheable(ttl: 3600, key: 'sparse:{5}')]
    public function sparseKey(int $id): string
    {
        return $this->cached(static fn (): string => 'sparse-' . $id);
    }

    #[Cacheable(ttl: 3600, key: 'obj:{0}')]
    public function objectKey(ValueHolder $holder): string
    {
        return $this->cached(function () use ($holder): string {
            ++$this->objectKeyCalls;
            return 'obj-' . $holder->value;
        });
    }

    public function getObjectKeyCalls(): int
    {
        return $this->objectKeyCalls;
    }
}

final class TestFacadeWithPlainExplicit
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600)]
    public function fetch(): string
    {
        return $this->cached(function (): string {
            ++$this->callCount;
            return 'fetched-' . $this->callCount;
        }, 'fetch');
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class TestFacadeAlphaShared
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600, key: 'alpha-{0}')]
    public function compute(int $id): string
    {
        return $this->cached(function () use ($id): string {
            ++$this->callCount;
            return 'alpha-' . $id;
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class TestFacadeBetaShared
{
    use CacheableTrait;

    private int $callCount = 0;

    #[Cacheable(ttl: 3600, key: 'beta-{0}')]
    public function compute(int $id): string
    {
        return $this->cached(function () use ($id): string {
            ++$this->callCount;
            return 'beta-' . $id;
        });
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

final class TestFacadeWithCachedButNoAttribute
{
    use CacheableTrait;

    private int $callCount = 0;

    public function plain(): string
    {
        return $this->cached(function (): string {
            ++$this->callCount;
            return 'plain-' . $this->callCount;
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
