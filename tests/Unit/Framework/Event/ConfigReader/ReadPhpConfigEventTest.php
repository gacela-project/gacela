<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\ConfigReader;

use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use PHPUnit\Framework\TestCase;

final class ReadPhpConfigEventTest extends TestCase
{
    private ReadPhpConfigEvent $event;

    protected function setUp(): void
    {
        $this->event = new ReadPhpConfigEvent('absolute/path');
    }

    public function test_absolute_path(): void
    {
        self::assertSame(
            'absolute/path',
            $this->event->absolutePath(),
        );
    }

    public function test_to_string(): void
    {
        self::assertStringContainsString(
            '{absolutePath:"absolute/path"}',
            $this->event->toString(),
        );
    }
}
