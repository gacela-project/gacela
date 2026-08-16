<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Attribute;

use Gacela\Framework\Event\Attribute\CacheableMissEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CacheableMissEventTest extends TestCase
{
    /**
     * The conversion is a pure function of the argument, which is the whole
     * reason the duration is carried in nanoseconds: measured against a live
     * clock it could only be asserted as "some non-negative number", and every
     * way of getting the arithmetic wrong satisfies that too.
     */
    #[DataProvider('durationProvider')]
    public function test_nanoseconds_convert_to_milliseconds(int $nanoseconds, float $expectedMilliseconds): void
    {
        $event = new CacheableMissEvent('App\\Wallet\\WalletFacade', 'getUser', 'key', $nanoseconds, 3600);

        self::assertSame($nanoseconds, $event->computeNanoseconds());
        self::assertSame($expectedMilliseconds, $event->computeMilliseconds());
    }

    /**
     * @return iterable<string, array{int, float}>
     */
    public static function durationProvider(): iterable
    {
        yield 'one millisecond' => [1_000_000, 1.0];
        yield 'two and a half' => [2_500_000, 2.5];
        yield 'sub-millisecond' => [1_500, 0.0015];
        yield 'nothing measurable' => [0, 0.0];
    }

    public function test_it_describes_itself(): void
    {
        $event = new CacheableMissEvent('App\\Wallet\\WalletFacade', 'getUser', 'the-key', 2_500_000, 60);

        $description = $event->toString();

        self::assertStringContainsString('App\\Wallet\\WalletFacade::getUser', $description);
        self::assertStringContainsString('the-key', $description);
        self::assertStringContainsString('2.500ms', $description);
        self::assertStringContainsString('ttl:60', $description);
    }
}
