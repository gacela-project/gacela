<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ClassNameFinderTest extends TestCase
{
    private const CANDIDATE = '\\valid\\class\\name';

    protected function setUp(): void
    {
        InMemoryCache::resetCache();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    public function test_no_rules(): void
    {
        $classNameFinder = new ClassNameFinder(
            $this->createStub(ClassValidatorInterface::class),
            [],
            $this->createStub(CacheInterface::class),
            [],
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = ['A', 'B'];
        $actual = $classNameFinder->findClassName($classInfo, $resolvableTypes);

        self::assertNull($actual);
    }

    public function test_rule_but_no_resolvable_types(): void
    {
        // With no resolvable types there is no candidate to build, so the
        // validator must not be consulted at all. The willReturn(true) this
        // used to carry was dead: the call it described never happened.
        $classValidator = $this->createMock(ClassValidatorInterface::class);
        $classValidator->expects(self::never())->method('isClassNameValid');

        $finderRule = $this->createStub(FinderRuleInterface::class);
        $finderRule->method('buildClassCandidate')->willReturn(self::CANDIDATE);

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [$finderRule],
            $this->createStub(CacheInterface::class),
            [],
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = [];
        $actual = $classNameFinder->findClassName($classInfo, $resolvableTypes);

        self::assertNull($actual);
    }

    public function test_rule_returns_invalid_class_name(): void
    {
        $classNameFinder = $this->finderWhoseCandidateIs(valid: false);

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $actual = $classNameFinder->findClassName($classInfo, ['A', 'B']);

        self::assertNull($actual);
    }

    public function test_rule_returns_valid_class_name(): void
    {
        $classNameFinder = $this->finderWhoseCandidateIs(valid: true);

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $actual = $classNameFinder->findClassName($classInfo, ['A', 'B']);

        self::assertSame(self::CANDIDATE, $actual);
    }

    public function test_caching_valid_class_name(): void
    {
        $classValidator = $this->createStub(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')->willReturn(true);

        $finderRule = $this->createMock(FinderRuleInterface::class);
        $finderRule->expects(self::once())
            ->method('buildClassCandidate')
            ->willReturn(self::CANDIDATE);

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [$finderRule],
            new InMemoryCache(ClassInfo::class),
            [],
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = ['A', 'B'];
        $classNameFinder->findClassName($classInfo, $resolvableTypes);
        $classNameFinder->findClassName($classInfo, $resolvableTypes);
        $classNameFinder->findClassName($classInfo, $resolvableTypes);
    }

    public function test_cached_hit_dispatches_class_name_cached_found_event(): void
    {
        \Gacela\Framework\Config\Config::resetInstance();

        $events = [];
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$events): void {
            $config->setFileCache(false);
            $config->registerGenericListener(static function (\Gacela\Framework\Event\GacelaEventInterface $event) use (&$events): void {
                $events[] = $event;
            });
        });

        $cache = new InMemoryCache(ClassInfo::class);
        $cache->put('cacheKey', '\cached\class');

        // The cached name is checked before it is trusted, so a hit only comes
        // back when the class it names still exists.
        $classValidator = $this->createStub(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')->willReturn(true);

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [],
            $cache,
            [],
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');

        self::assertSame('\cached\class', $classNameFinder->findClassName($classInfo, ['A']));

        $cachedFoundEvents = array_filter(
            $events,
            static fn (\Gacela\Framework\Event\GacelaEventInterface $event): bool => $event instanceof \Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameCachedFoundEvent,
        );
        self::assertCount(1, $cachedFoundEvents);
    }

    /**
     * A persisted cache entry outlives the class it names.
     *
     * Rename or delete a Factory and deploy without clearing the cache dir and
     * the entry still resolves -- to a class that is gone. Nothing downstream
     * catches it: the container answers `null` for a class it cannot find, and
     * `AbstractClassResolver::createInstance()` is typed to return an object,
     * so the run dies on a `TypeError` naming neither the class, nor the cache,
     * nor `cache:clear`.
     *
     * So a hit is checked before it is trusted, and a stale one falls through
     * to the finder rules, which overwrite it. The user does nothing.
     */
    public function test_a_cached_class_that_no_longer_exists_is_refound_not_returned(): void
    {
        $cache = new InMemoryCache(ClassInfo::class);
        $cache->put('cacheKey', '\gone\class');

        $classValidator = $this->createStub(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')
            ->willReturnCallback(static fn (string $className): bool => $className === self::CANDIDATE);

        $finderRule = $this->createStub(FinderRuleInterface::class);
        $finderRule->method('buildClassCandidate')->willReturn(self::CANDIDATE);

        $classNameFinder = new ClassNameFinder($classValidator, [$finderRule], $cache, []);
        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');

        self::assertSame(self::CANDIDATE, $classNameFinder->findClassName($classInfo, ['A']));

        // Self-healing: the next resolution must not pay for the same miss, so
        // the stale entry is replaced rather than merely bypassed.
        self::assertSame(self::CANDIDATE, $cache->get('cacheKey'));
    }

    /**
     * The stale entry must not survive as a fallback when nothing replaces it
     * either: returning a class that does not exist is the failure, and having
     * found no better answer does not make it a better answer.
     */
    public function test_a_stale_cached_class_is_not_returned_when_no_rule_matches(): void
    {
        $cache = new InMemoryCache(ClassInfo::class);
        $cache->put('cacheKey', '\gone\class');

        $classValidator = $this->createStub(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')->willReturn(false);

        $classNameFinder = new ClassNameFinder($classValidator, [], $cache, []);
        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');

        self::assertNull($classNameFinder->findClassName($classInfo, ['A']));
    }

    /**
     * The two rule tests differ only in the validator's verdict; everything
     * else -- one rule, one candidate, no cache -- is the same wiring.
     */
    private function finderWhoseCandidateIs(bool $valid): ClassNameFinder
    {
        $classValidator = $this->createMock(ClassValidatorInterface::class);
        $classValidator->expects(self::atLeastOnce())
            ->method('isClassNameValid')
            ->with(self::CANDIDATE)
            ->willReturn($valid);

        $finderRule = $this->createStub(FinderRuleInterface::class);
        $finderRule->method('buildClassCandidate')->willReturn(self::CANDIDATE);

        return new ClassNameFinder(
            $classValidator,
            [$finderRule],
            $this->createStub(CacheInterface::class),
            [],
        );
    }
}
