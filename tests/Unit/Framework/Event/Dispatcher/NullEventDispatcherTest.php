<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NullEventDispatcherTest extends TestCase
{
    public function test_has_no_listeners_for_any_event_class(): void
    {
        $dispatcher = new NullEventDispatcher();

        self::assertFalse($dispatcher->hasListeners(ReadPhpConfigEvent::class));
        self::assertFalse($dispatcher->hasListeners(stdClass::class));
    }
}
