<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\ConfigReader;

use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use PHPUnit\Framework\TestCase;

final class ReadPhpConfigEventTest extends TestCase
{
    public function test_to_string(): void
    {
        $event = new ReadPhpConfigEvent('absolute/path');

        self::assertStringContainsString('{absolutePath:"absolute/path"}', $event->toString());
    }

    public function test_absolute_path(): void
    {
        $event = new ReadPhpConfigEvent('absolute/path');

        self::assertSame('absolute/path', $event->absolutePath());
    }
}
