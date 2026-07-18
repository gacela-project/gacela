<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\CacheWarm;

use Gacela\Console\Application\CacheWarm\BytesFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BytesFormatterTest extends TestCase
{
    #[DataProvider('boundaryValuesProvider')]
    public function test_formats_boundary_values(int $bytes, string $expected): void
    {
        self::assertSame($expected, BytesFormatter::format($bytes));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function boundaryValuesProvider(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'largest bytes' => [1023, '1023 B'];
        yield 'kb boundary' => [1024, '1.00 KB'];
        yield 'largest kb rounds up to 1024.00' => [1048575, '1024.00 KB'];
        yield 'mb boundary' => [1048576, '1.00 MB'];
        yield 'large non-round mb' => [1572864, '1.50 MB'];
    }
}
