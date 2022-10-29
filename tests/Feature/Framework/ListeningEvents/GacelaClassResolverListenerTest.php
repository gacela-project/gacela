<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\EventListener\ClassResolver\GacelaClassResolverListener;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassTryFormParentEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedDefaultClassEvent;
use Gacela\Framework\EventListener\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class GacelaClassResolverListenerTest extends TestCase
{
    /** @var list<GacelaEventInterface> */
    private static array $inMemoryEvents = [];

    public function setUp(): void
    {
        self::$inMemoryEvents = [];

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addEventListener(
                GacelaClassResolverListener::class,
                static function (GacelaEventInterface $event): void {
                    self::$inMemoryEvents[] = $event;
                }
            );
        });
    }

    public function test_resolved_class_created(): void
    {
        $facade = new Module\Facade();
        $facade->doString();

        self::assertEquals([
            new ResolvedClassCreatedEvent(ClassInfo::from(Module\Facade::class, 'Factory')),
        ], self::$inMemoryEvents);
    }

    public function test_resolved_class_cached(): void
    {
        $facade = new Module\Facade();
        $facade->doString();

        $facade = new Module\Facade();
        $facade->doString();

        self::assertEquals([
            new ResolvedClassCreatedEvent(ClassInfo::from(Module\Facade::class, 'Factory')),
            new ResolvedClassCachedEvent(ClassInfo::from(Module\Facade::class, 'Factory')),
        ], self::$inMemoryEvents);
    }

    public function test_resolved_parent_and_default_class(): void
    {
        $factory = new Module\Factory();
        $factory->getConfig();

        self::assertEquals([
            new ResolvedClassTryFormParentEvent(ClassInfo::from(Module\Factory::class, 'Config')),
            new ResolvedDefaultClassEvent(ClassInfo::from(AbstractFactory::class, 'Config')),
        ], self::$inMemoryEvents);

        // And again would simply load the cached event
        self::$inMemoryEvents = [];
        $factory = new Module\Factory();
        $factory->getConfig();

        self::assertEquals([
            new ResolvedClassCachedEvent(ClassInfo::from(Module\Factory::class, 'Config')),
        ], self::$inMemoryEvents);
    }
}
