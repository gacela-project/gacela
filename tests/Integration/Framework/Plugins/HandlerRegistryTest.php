<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Plugins;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use Gacela\Framework\Plugins\HandlerRegistry;
use Gacela\Framework\Plugins\LazyHandlerRegistry;
use GacelaTest\Integration\Framework\Plugins\Handler\ConcreteHandlerA;
use GacelaTest\Integration\Framework\Plugins\Handler\ConcreteHandlerB;
use GacelaTest\Integration\Framework\Plugins\Handler\CountingHandler;
use GacelaTest\Integration\Framework\Plugins\Handler\HandlerWithDependency;
use GacelaTest\Integration\Framework\Plugins\Handler\InjectedDependency;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class HandlerRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $reflection->getMethod('resetCache')->invoke(null);
        CountingHandler::$instantiations = 0;
    }

    public function test_registry_is_resolvable_from_container(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('dispatcher', [
                'a' => ConcreteHandlerA::class,
                'b' => ConcreteHandlerB::class,
            ]);
        });

        $container = Container::withConfig(Config::getInstance());

        $registry = $container->get('dispatcher');

        self::assertInstanceOf(HandlerRegistry::class, $registry);
        self::assertInstanceOf(LazyHandlerRegistry::class, $registry);
    }

    public function test_get_returns_handler_for_registered_key(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('dispatcher', [
                'a' => ConcreteHandlerA::class,
                'b' => ConcreteHandlerB::class,
            ]);
        });

        /** @var HandlerRegistry $registry */
        $registry = Container::withConfig(Config::getInstance())->get('dispatcher');

        self::assertInstanceOf(ConcreteHandlerA::class, $registry->get('a'));
        self::assertInstanceOf(ConcreteHandlerB::class, $registry->get('b'));
    }

    public function test_handler_constructor_dependencies_are_auto_wired(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('dispatcher', [
                'needs-dep' => HandlerWithDependency::class,
            ]);
        });

        /** @var HandlerRegistry $registry */
        $registry = Container::withConfig(Config::getInstance())->get('dispatcher');

        /** @var HandlerWithDependency $handler */
        $handler = $registry->get('needs-dep');

        self::assertInstanceOf(HandlerWithDependency::class, $handler);
        self::assertInstanceOf(InjectedDependency::class, $handler->dependency);
    }

    public function test_handlers_are_instantiated_lazily(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('dispatcher', [
                'only' => CountingHandler::class,
            ]);
        });

        /** @var HandlerRegistry $registry */
        $registry = Container::withConfig(Config::getInstance())->get('dispatcher');

        self::assertSame(0, CountingHandler::$instantiations);

        $registry->get('only');
        $registry->get('only');

        self::assertSame(1, CountingHandler::$instantiations);
    }

    public function test_unknown_key_throws(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('dispatcher', [
                'a' => ConcreteHandlerA::class,
            ]);
        });

        /** @var HandlerRegistry $registry */
        $registry = Container::withConfig(Config::getInstance())->get('dispatcher');

        $this->expectException(OutOfBoundsException::class);

        $registry->get('missing');
    }

    public function test_multiple_registries_are_independently_resolvable(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addHandlerRegistry('dispatcher-a', ['k' => ConcreteHandlerA::class]);
            $config->addHandlerRegistry('dispatcher-b', ['k' => ConcreteHandlerB::class]);
        });

        $container = Container::withConfig(Config::getInstance());

        /** @var HandlerRegistry $registryA */
        $registryA = $container->get('dispatcher-a');
        /** @var HandlerRegistry $registryB */
        $registryB = $container->get('dispatcher-b');

        self::assertInstanceOf(ConcreteHandlerA::class, $registryA->get('k'));
        self::assertInstanceOf(ConcreteHandlerB::class, $registryB->get('k'));
    }
}
