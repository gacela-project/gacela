<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;

final class SetupGacelaTest extends TestCase
{
    public function test_null_event_dispatcher(): void
    {
        $config = new GacelaConfig();
        $setup = SetupGacela::fromGacelaConfig($config);

        self::assertInstanceOf(NullEventDispatcher::class, $setup->getEventDispatcher());
        $setup->getEventDispatcher()->dispatch(new FakeEvent());
    }

    public function test_configurable_event_dispatcher(): void
    {
        $listenerDispatched = false;
        $listener = static function (GacelaEventInterface $event) use (&$listenerDispatched): void {
            self::assertInstanceOf(FakeEvent::class, $event);
            $listenerDispatched = true;
        };

        $config = (new GacelaConfig())->registerGenericListener($listener);

        $setup = SetupGacela::fromGacelaConfig($config);

        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());

        self::assertFalse($listenerDispatched);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());
        self::assertTrue($listenerDispatched);
    }

    public function test_combine_event_dispatcher(): void
    {
        $listenerDispatched1 = false;
        $listenerDispatched2 = false;

        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(
                FakeEvent::class,
                static function (GacelaEventInterface $event) use (&$listenerDispatched1): void {
                    self::assertInstanceOf(FakeEvent::class, $event);
                    $listenerDispatched1 = true;
                }
            )
        );

        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(
                FakeEvent::class,
                static function (GacelaEventInterface $event) use (&$listenerDispatched2): void {
                    self::assertInstanceOf(FakeEvent::class, $event);
                    $listenerDispatched2 = true;
                }
            )
        );
        $setup->combine($setup2);

        self::assertFalse($listenerDispatched1);
        self::assertFalse($listenerDispatched2);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());
        self::assertTrue($listenerDispatched1);
        self::assertTrue($listenerDispatched2);
    }
}
