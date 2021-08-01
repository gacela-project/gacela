<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\GlobalKey;
use PHPUnit\Framework\TestCase;

final class GlobalKeyTest extends TestCase
{
    public function test_using_the_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('App\ModuleExample\ModuleFacade')
        );
    }

    public function test_starting_with_slash_and_using_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('\App\ModuleExample\ModuleFacade')
        );
    }

    public function test_not_using_the_module_prefix_in_the_class(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('App\ModuleExample\Facade')
        );
    }

    public function test_starting_with_slash_and_not_using_the_module_prefix_in_the_class(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('\App\ModuleExample\Facade')
        );
    }

    public function test_dependency_provider_using_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\DependencyProvider',
            GlobalKey::fromClassName('\App\ModuleExample\ModuleDependencyProvider')
        );
    }

    public function test_dependency_provider_not_using_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\DependencyProvider',
            GlobalKey::fromClassName('\App\ModuleExample\DependencyProvider')
        );
    }
}
