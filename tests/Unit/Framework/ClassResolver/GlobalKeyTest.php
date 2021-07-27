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
            '\App\Module\Facade',
            GlobalKey::fromClassName('App\Module\ModuleFacade')
        );
    }

    public function test_starting_with_slash_and_using_module_prefix(): void
    {
        self::assertSame(
            '\App\Module\Facade',
            GlobalKey::fromClassName('\App\Module\ModuleFacade')
        );
    }

    public function test_not_using_the_module_prefix_in_the_class(): void
    {
        self::assertSame(
            '\App\Module\Facade',
            GlobalKey::fromClassName('App\Module\Facade')
        );
    }

    public function test_starting_with_slash_and_not_using_the_module_prefix_in_the_class(): void
    {
        self::assertSame(
            '\App\Module\Facade',
            GlobalKey::fromClassName('\App\Module\Facade')
        );
    }


    public function test_dependency_provider_using_module_prefix(): void
    {
        self::assertSame(
            '\App\Module\DependencyProvider',
            GlobalKey::fromClassName('\App\Module\ModuleDependencyProvider')
        );
    }

    public function test_dependency_provider_not_using_module_prefix(): void
    {
        self::assertSame(
            '\App\Module\DependencyProvider',
            GlobalKey::fromClassName('\App\Module\DependencyProvider')
        );
    }
}
