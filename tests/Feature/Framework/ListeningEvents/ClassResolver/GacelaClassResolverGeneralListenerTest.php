<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\ClassResolver;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameInvalidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameValidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassTriedFromParentEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedCreatedDefaultClassEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class GacelaClassResolverGeneralListenerTest extends TestCase
{
    /** @var list<GacelaEventInterface> */
    private static array $inMemoryEvents = [];

    public function setUp(): void
    {
        self::$inMemoryEvents = [];

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();

            $config->registerGenericListener([$this, 'saveInMemoryEvent']);
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
            new ClassNameInMemoryCacheCreatedEvent(),
            new ClassNameInvalidCandidateFoundEvent('\GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\ModuleFactory'),
            new ClassNameValidCandidateFoundEvent('\GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\Factory'),
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
            new ClassNameInMemoryCacheCreatedEvent(),
            new ClassNameInvalidCandidateFoundEvent('\GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\ModuleFactory'),
            new ClassNameValidCandidateFoundEvent('\GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\Factory'),
            new ResolvedClassCreatedEvent(ClassInfo::from(Module\Facade::class, 'Factory')),
            new ResolvedClassCachedEvent(ClassInfo::from(Module\Facade::class, 'Factory')),
        ], self::$inMemoryEvents);
    }

    public function test_resolved_parent_and_default_class(): void
    {
        $factory = new Module\Factory();
        $factory->getConfig();

        self::assertEquals([
            new ClassNameInMemoryCacheCreatedEvent(),
            new ClassNameInvalidCandidateFoundEvent('\GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\ModuleConfig'),
            new ClassNameInvalidCandidateFoundEvent('\GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\Config'),
            new ClassNameNotFoundEvent(ClassInfo::from(Module\Factory::class, 'Config'), ['Config']),
            new ResolvedClassTriedFromParentEvent(ClassInfo::from(Module\Factory::class, 'Config')),
            new ClassNameInvalidCandidateFoundEvent('\Gacela\Framework\FrameworkConfig'),
            new ClassNameInvalidCandidateFoundEvent('\Gacela\Framework\Config'),
            new ClassNameNotFoundEvent(ClassInfo::from(AbstractFactory::class, 'Config'), ['Config']),
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
