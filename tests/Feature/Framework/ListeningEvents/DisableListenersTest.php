<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\EventListener\ClassResolver\GacelaClassResolverListener;
use Gacela\Framework\EventListener\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DisableListenersTest extends TestCase
{
    public function test_disable_class_resolver_listener(): void
    {
        /** @var list<GacelaEventInterface> $inMemoryEvents */
        $inMemoryEvents = [];

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$inMemoryEvents): void {
            $config->disableEventListeners();

            $config->addEventListener(
                GacelaClassResolverListener::class,
                static function (GacelaEventInterface $event) use (&$inMemoryEvents): void {
                    $inMemoryEvents[] = $event;
                    throw new RuntimeException('This code should never be called');
                }
            );
        });

        $facade = new Module\Facade();
        $facade->doString();

        $facade = new Module\Facade();
        $facade->doString();

        self::assertEmpty($inMemoryEvents);
    }
}
