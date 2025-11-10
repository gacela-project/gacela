<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class AliasServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_alias_resolves_to_original_service(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding('original-service', static fn (): stdClass => new stdClass());
            $config->addAlias('alias-service', 'original-service');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $alias = $container->get('alias-service');

        self::assertInstanceOf(stdClass::class, $alias, 'Alias should resolve using the binding');
    }

    public function test_multiple_aliases_for_same_factory(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addFactory('factory', static fn (): stdClass => new stdClass());
            $config->addAlias('alias1', 'factory');
            $config->addAlias('alias2', 'factory');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('alias1');
        $instance2 = $container->get('alias2');

        self::assertInstanceOf(stdClass::class, $instance1);
        self::assertInstanceOf(stdClass::class, $instance2);
        self::assertNotSame($instance1, $instance2, 'Aliases to factory should each return new instances');
    }

    public function test_alias_with_factory_service(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addFactory('factory-service', static fn (): stdClass => new stdClass());
            $config->addAlias('alias-factory', 'factory-service');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('factory-service');
        $instance2 = $container->get('alias-factory');

        self::assertInstanceOf(stdClass::class, $instance1);
        self::assertInstanceOf(stdClass::class, $instance2);
        self::assertNotSame($instance1, $instance2, 'Factory service should return new instances even through alias');
    }
}
