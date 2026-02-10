<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class LazyServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_lazy_service_is_not_instantiated_until_accessed(): void
    {
        $instantiated = false;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$instantiated): void {
            $config->addLazy('expensive-service', static function () use (&$instantiated): stdClass {
                $instantiated = true;
                return new stdClass();
            });
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        // Service should not be instantiated yet
        self::assertFalse($instantiated, 'Lazy service should not be instantiated until accessed');

        // Access the service
        $service = $container->get('expensive-service');

        // Now it should be instantiated
        self::assertTrue($instantiated, 'Lazy service should be instantiated after first access');
        self::assertInstanceOf(stdClass::class, $service);
    }

    public function test_lazy_service_returns_new_instance_each_time(): void
    {
        $counter = 0;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$counter): void {
            $config->addLazy('counter-service', static function () use (&$counter): stdClass {
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
        self::assertNotSame($instance1, $instance2);
    }

    public function test_lazy_service_with_container_dependencies(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding('dependency', static fn () => (object)['name' => 'Dependency']);
            $config->addLazy('service-with-dep', static function (Container $c): stdClass {
                $obj = new stdClass();
                $obj->dependency = $c->get('dependency');
                return $obj;
            });
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $service = $container->get('service-with-dep');

        self::assertInstanceOf(stdClass::class, $service);
        self::assertSame('Dependency', $service->dependency->name);
    }

    public function test_multiple_lazy_services_are_independent(): void
    {
        $instantiatedA = false;
        $instantiatedB = false;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$instantiatedA, &$instantiatedB): void {
            $config->addLazy('service-a', static function () use (&$instantiatedA): stdClass {
                $instantiatedA = true;
                return (object)['type' => 'A'];
            });
            $config->addLazy('service-b', static function () use (&$instantiatedB): stdClass {
                $instantiatedB = true;
                return (object)['type' => 'B'];
            });
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        // Access only service A
        $serviceA = $container->get('service-a');

        self::assertTrue($instantiatedA, 'Service A should be instantiated');
        self::assertFalse($instantiatedB, 'Service B should not be instantiated yet');
        self::assertSame('A', $serviceA->type);

        // Now access service B
        $serviceB = $container->get('service-b');

        self::assertTrue($instantiatedB, 'Service B should now be instantiated');
        self::assertSame('B', $serviceB->type);
    }
}
