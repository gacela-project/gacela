<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\CacheExpiry;
use PHPUnit\Framework\TestCase;

final class CacheExpiryTest extends TestCase
{
    public function test_zero_and_overflowing_lifetimes_have_no_expiry(): void
    {
        self::assertNull(CacheExpiry::fromTtl(0));
        self::assertNull(CacheExpiry::fromTtl(PHP_INT_MAX));
    }

    public function test_a_positive_lifetime_has_an_expiry(): void
    {
        $before = time();
        $expiry = CacheExpiry::fromTtl(60);

        self::assertNotNull($expiry);
        self::assertGreaterThanOrEqual($before + 60, $expiry);
        self::assertLessThanOrEqual(time() + 60, $expiry);
    }

    public function test_a_large_lifetime_that_fits_keeps_its_expiry(): void
    {
        $expiry = CacheExpiry::fromTtl(PHP_INT_MAX - time() - 60);

        self::assertNotNull($expiry);
        self::assertGreaterThanOrEqual(PHP_INT_MAX - 60, $expiry);
    }

    public function test_negative_lifetimes_are_already_expired(): void
    {
        self::assertLessThanOrEqual(time(), CacheExpiry::fromTtl(-1));
        self::assertLessThanOrEqual(time(), CacheExpiry::fromTtl(PHP_INT_MIN));
    }
}
