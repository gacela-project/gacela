<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class FactoryServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_factory_service_returns_new_instance_each_time(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addFactory('test-service', static fn (): stdClass => new stdClass());
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('test-service');
        $instance2 = $container->get('test-service');

        self::assertInstanceOf(stdClass::class, $instance1);
        self::assertInstanceOf(stdClass::class, $instance2);
        self::assertNotSame($instance1, $instance2, 'Factory should return a new instance each time');
    }

    public function test_factory_service_with_dependencies(): void
    {
        $counter = 0;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$counter): void {
            $config->addFactory('counter-service', static function () use (&$counter): stdClass {
                ++$counter;
                $obj = new stdClass();
                $obj->count = $counter;
                return $obj;
            });
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('counter-service');
        $instance2 = $container->get('counter-service');
        $instance3 = $container->get('counter-service');

        self::assertSame(1, $instance1->count);
        self::assertSame(2, $instance2->count);
        self::assertSame(3, $instance3->count);
    }

    public function test_multiple_factory_services(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addFactory('service-a', static fn () => (object)['type' => 'A']);
            $config->addFactory('service-b', static fn () => (object)['type' => 'B']);
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $serviceA1 = $container->get('service-a');
        $serviceA2 = $container->get('service-a');
        $serviceB1 = $container->get('service-b');

        self::assertSame('A', $serviceA1->type);
        self::assertSame('A', $serviceA2->type);
        self::assertSame('B', $serviceB1->type);
        self::assertNotSame($serviceA1, $serviceA2);
    }
}
