<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassTriedFromParentEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedCreatedDefaultClassEvent;
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

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();

            $config->registerListener(ResolvedClassCachedEvent::class, [$this, 'saveInMemoryEvent']);
            $config->registerListener(ResolvedClassCreatedEvent::class, [$this, 'saveInMemoryEvent']);
            $config->registerListener(ResolvedClassTriedFromParentEvent::class, [$this, 'saveInMemoryEvent']);
            $config->registerListener(ResolvedCreatedDefaultClassEvent::class, [$this, 'saveInMemoryEvent']);
        });
    }

    public function saveInMemoryEvent(GacelaEventInterface $event): void
    {
        self::$inMemoryEvents[] = $event;
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
            new ResolvedClassTriedFromParentEvent(ClassInfo::from(Module\Factory::class, 'Config')),
            new ResolvedCreatedDefaultClassEvent(ClassInfo::from(AbstractFactory::class, 'Config')),
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
