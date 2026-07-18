<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use PHPUnit\Framework\TestCase;

final class InMemoryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        InMemoryCache::resetCache();
    }

    protected function tearDown(): void
    {
        InMemoryCache::resetCache();
    }

    public function test_get_all_returns_entries_for_the_cache_key(): void
    {
        $cache = new InMemoryCache('some-key');
        $cache->put('A', 'ResolvedA');
        $cache->put('B', 'ResolvedB');

        self::assertSame(['A' => 'ResolvedA', 'B' => 'ResolvedB'], $cache->getAll());
    }

    public function test_get_all_is_empty_for_untouched_key(): void
    {
        self::assertSame([], (new InMemoryCache('fresh-key'))->getAll());
    }
}
