<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\EventListener\Event\GacelaEventInterface;
use Gacela\Framework\EventListener\Event\ResolvedClassCachedEvent;
use Gacela\Framework\EventListener\Event\ResolvedClassCreatedEvent;
use Gacela\Framework\EventListener\Event\ResolvedClassTryFormParentEvent;
use Gacela\Framework\EventListener\Event\ResolvedDefaultClassEvent;
use Gacela\Framework\EventListener\GacelaClassResolverListener;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
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
            new ResolvedDefaultClassEvent(ClassInfo::from(get_parent_class(Module\Factory::class), 'Config')),
        ], self::$inMemoryEvents);

        // And again would simply load the cached event
        self::$inMemoryEvents = [];
        $factory = new Module\Factory();
        $factory->getConfig();

        self::assertEquals([
            new ResolvedClassTryFormParentEvent(ClassInfo::from(Module\Factory::class, 'Config')),
            new ResolvedClassCachedEvent(ClassInfo::from(get_parent_class(Module\Factory::class), 'Config')),
        ], self::$inMemoryEvents);
    }
}
