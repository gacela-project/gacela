<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ResolvableType;
use PHPUnit\Framework\TestCase;

final class ResolvableTypeTest extends TestCase
{
    public function test_empty_class_name(): void
    {
        $actual = ResolvableType::fromClassName('');

        self::assertSame('', $actual->moduleName());
        self::assertSame('', $actual->resolvableType());
    }

    public function test_custom_resolvable_name(): void
    {
        $actual = ResolvableType::fromClassName('ModuleName\\CustomResolvable');

        self::assertSame('ModuleName', $actual->moduleName());
        self::assertSame('CustomResolvable', $actual->resolvableType());
    }

    public function test_using_module_prefix_custom_without_any_resolvable_type(): void
    {
        $actual = ResolvableType::fromClassName('Custom');

        self::assertSame('', $actual->moduleName());
        self::assertSame('Custom', $actual->resolvableType());
    }

    public function test_not_using_the_module_prefix_facade(): void
    {
        $actual = ResolvableType::fromClassName('Facade');

        self::assertSame('', $actual->moduleName());
        self::assertSame('Facade', $actual->resolvableType());
    }

    public function test_not_using_the_module_prefix_factory(): void
    {
        $actual = ResolvableType::fromClassName('Factory');

        self::assertSame('', $actual->moduleName());
        self::assertSame('Factory', $actual->resolvableType());
    }

    public function test_not_using_the_module_prefix_config(): void
    {
        $actual = ResolvableType::fromClassName('Config');

        self::assertSame('', $actual->moduleName());
        self::assertSame('Config', $actual->resolvableType());
    }

    public function test_not_using_module_prefix_dependency_provider(): void
    {
        $actual = ResolvableType::fromClassName('Provider');

        self::assertSame('', $actual->moduleName());
        self::assertSame('Provider', $actual->resolvableType());
    }

    public function test_using_the_module_prefix_facade(): void
    {
        $actual = ResolvableType::fromClassName('ModuleExampleFacade');

        self::assertSame('ModuleExample', $actual->moduleName());
        self::assertSame('Facade', $actual->resolvableType());
    }

    public function test_using_the_module_prefix_factory(): void
    {
        $actual = ResolvableType::fromClassName('ModuleExampleFactory');

        self::assertSame('ModuleExample', $actual->moduleName());
        self::assertSame('Factory', $actual->resolvableType());
    }

    public function test_using_the_module_prefix_config(): void
    {
        $actual = ResolvableType::fromClassName('ModuleExampleConfig');

        self::assertSame('ModuleExample', $actual->moduleName());
        self::assertSame('Config', $actual->resolvableType());
    }

    public function test_using_module_prefix_dependency_provider(): void
    {
        $actual = ResolvableType::fromClassName('ModuleExampleProvider');

        self::assertSame('ModuleExample', $actual->moduleName());
        self::assertSame('Provider', $actual->resolvableType());
    }
}
