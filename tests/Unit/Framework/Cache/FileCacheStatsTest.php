<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\FileCacheStats;
use PHPUnit\Framework\TestCase;

final class FileCacheStatsTest extends TestCase
{
    public function test_it_holds_entries_and_bytes(): void
    {
        $stats = new FileCacheStats(entries: 5, bytes: 1024, oldestAt: null, newestAt: null);

        self::assertSame(5, $stats->entries);
        self::assertSame(1024, $stats->bytes);
        self::assertNull($stats->oldestAt);
        self::assertNull($stats->newestAt);
    }

    public function test_it_holds_oldest_and_newest_timestamps(): void
    {
        $stats = new FileCacheStats(entries: 2, bytes: 256, oldestAt: 1000, newestAt: 2000);

        self::assertSame(1000, $stats->oldestAt);
        self::assertSame(2000, $stats->newestAt);
    }

    public function test_empty_cache_has_zero_entries_and_bytes(): void
    {
        $stats = new FileCacheStats(entries: 0, bytes: 0, oldestAt: null, newestAt: null);

        self::assertSame(0, $stats->entries);
        self::assertSame(0, $stats->bytes);
    }
}
