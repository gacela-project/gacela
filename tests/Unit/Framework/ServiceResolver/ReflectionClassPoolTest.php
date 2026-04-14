<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ServiceResolver;

use Gacela\Framework\ServiceResolver\ReflectionClassPool;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ReflectionClassPoolTest extends TestCase
{
    protected function setUp(): void
    {
        ReflectionClassPool::reset();
    }

    public function test_returns_same_instance_for_same_class(): void
    {
        $first = ReflectionClassPool::get(stdClass::class);
        $second = ReflectionClassPool::get(stdClass::class);

        self::assertSame($first, $second);
    }

    public function test_reset_clears_the_cache(): void
    {
        $first = ReflectionClassPool::get(stdClass::class);
        ReflectionClassPool::reset();
        $second = ReflectionClassPool::get(stdClass::class);

        self::assertNotSame($first, $second);
    }
}
