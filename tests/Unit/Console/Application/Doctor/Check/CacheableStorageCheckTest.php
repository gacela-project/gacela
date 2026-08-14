<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\CacheableStorageCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\CacheableSubject;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\TwiceCacheableSubject;
use PHPUnit\Framework\TestCase;

/**
 * The default `#[Cacheable]` backend lives and dies with the process. Under
 * PHP-FPM that is one request, so a method annotated for an hour's TTL is
 * recomputed every time and the attribute buys nothing -- silently, because a
 * cache that misses looks exactly like one never asked to hold anything.
 */
final class CacheableStorageCheckTest extends TestCase
{
    public function test_it_names_the_cacheable_methods_running_on_the_default_storage(): void
    {
        $result = (new CacheableStorageCheck([$this->moduleWithCacheable()], false))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([CacheableSubject::class . '::cached()'], $result->details);
        self::assertStringContainsString('setStorage()', $result->remediation);
    }

    /**
     * The whole sentence. The default is right for anything that is not
     * per-request — a CLI batch, a queue worker, a server whose process
     * outlives the work — so the remediation says so rather than presenting a
     * registered backend as the only correct answer, and a substring assertion
     * would pass for half of it in the wrong order.
     */
    public function test_the_remediation_admits_the_default_is_sometimes_right(): void
    {
        $result = (new CacheableStorageCheck([$this->moduleWithCacheable()], false))->run();

        self::assertSame(
            'the default storage lives and dies with the process, so under PHP-FPM these are '
            . 'recomputed every request — register a cross-request backend with '
            . '`CacheableConfig::setStorage()`, or ignore this where the process outlives the work',
            $result->remediation,
        );
    }

    /**
     * Every annotated method is named, not just the first: a facade with two of
     * them is ordinary, and reporting one would send the reader back for the
     * second.
     */
    public function test_every_cacheable_method_is_named(): void
    {
        $module = new AppModule('App\\Shop', 'Shop', TwiceCacheableSubject::class);

        $result = (new CacheableStorageCheck([$module], false))->run();

        self::assertSame([
            TwiceCacheableSubject::class . '::first()',
            TwiceCacheableSubject::class . '::second()',
        ], $result->details);
    }

    /**
     * And every pillar, not just the Facade -- with the annotated methods on
     * two different pillars, keeping only the first drops half the report.
     */
    public function test_cacheable_methods_on_two_pillars_are_both_named(): void
    {
        $module = new AppModule('App\\Shop', 'Shop', CacheableSubject::class, TwiceCacheableSubject::class);

        self::assertCount(3, (new CacheableStorageCheck([$module], false))->run()->details);
    }

    public function test_a_registered_backend_passes_and_counts_what_it_covers(): void
    {
        $result = (new CacheableStorageCheck([$this->moduleWithCacheable()], true))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 #[Cacheable] method(s) on a registered backend'], $result->details);
    }

    /**
     * Without this the check would warn at every project that never asked for
     * caching, which is most of them.
     */
    public function test_a_project_with_no_cacheable_method_is_not_warned_at(): void
    {
        $module = new AppModule('App\\Plain', 'Plain', self::class);

        $result = (new CacheableStorageCheck([$module], false))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no #[Cacheable] methods to store'], $result->details);
    }

    /**
     * Only the annotated ones. A class with both is the ordinary case, and
     * counting the plain method would inflate every report.
     */
    public function test_a_method_without_the_attribute_is_not_counted(): void
    {
        $result = (new CacheableStorageCheck([$this->moduleWithCacheable()], false))->run();

        self::assertCount(1, $result->details);
        self::assertStringNotContainsString('plain()', $result->details[0]);
    }

    /**
     * Every pillar is read, not only the Facade: `#[Cacheable]` is documented
     * on a Facade but nothing stops a Factory carrying one.
     */
    public function test_a_cacheable_method_on_a_factory_is_found_too(): void
    {
        $module = new AppModule('App\\Shop', 'Shop', self::class, CacheableSubject::class);

        $result = (new CacheableStorageCheck([$module], false))->run();

        self::assertSame([CacheableSubject::class . '::cached()'], $result->details);
    }

    private function moduleWithCacheable(): AppModule
    {
        return new AppModule('App\\Shop', 'Shop', CacheableSubject::class);
    }
}
