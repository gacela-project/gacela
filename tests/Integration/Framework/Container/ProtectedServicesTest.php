<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProtectedServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_protected_service_returns_closure_not_invoked(): void
    {
        $callable = static fn (): string => 'value';

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($callable): void {
            $config->addProtected('test-service', $callable);
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $result = $container->get('test-service');

        self::assertSame($callable, $result, 'Protected service should return the closure itself');
    }

    public function test_protected_service_cannot_be_extended(): void
    {
        $this->expectException(\Gacela\Container\Exception\ContainerException::class);
        $this->expectExceptionMessage("The instance 'test-service' is protected and cannot be extended");

        $callable = static fn (): string => 'original';

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($callable): void {
            $config->addProtected('test-service', $callable);
            $config->extendService('test-service', static fn ($c, $previous): Closure => static fn (): string => 'extended');
        });

        Container::withConfig(\Gacela\Framework\Config\Config::getInstance());
    }

    public function test_multiple_protected_services(): void
    {
        $callableA = static fn (): string => 'A';
        $callableB = static fn (): string => 'B';

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($callableA, $callableB): void {
            $config->addProtected('service-a', $callableA);
            $config->addProtected('service-b', $callableB);
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $resultA = $container->get('service-a');
        $resultB = $container->get('service-b');

        self::assertSame($callableA, $resultA);
        self::assertSame($callableB, $resultB);
    }
}
