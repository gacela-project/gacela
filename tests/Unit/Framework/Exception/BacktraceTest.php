<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Exception;

use Gacela\Framework\Exception\Backtrace;
use PHPUnit\Framework\TestCase;

final class BacktraceTest extends TestCase
{
    public function test_backtrace(): void
    {
        $backtrace = $this->createPartialMock(Backtrace::class, ['getBacktraces']);
        $backtrace->method('getBacktraces')->willReturn([
            ['line' => 10, 'file' => 'file-name-1'],
            ['line' => 11, 'file' => 'file-name-1'],
            ['line' => 20, 'file' => 'file-name-2'],
        ]);

        $expected = <<<TXT
file-name-1:10
file-name-1:11
file-name-2:20

TXT;
        self::assertEquals($expected, $backtrace->get());
    }
}
