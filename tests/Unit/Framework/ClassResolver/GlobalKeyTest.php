<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\GlobalKey;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class GlobalKeyTest extends TestCase
{
    public function test_empty_class_name(): void
    {
        self::assertSame('\\', GlobalKey::fromClassName(''));
    }

    public function test_only_class_name(): void
    {
        self::assertSame('\ClassName', GlobalKey::fromClassName('ClassName'));
    }

    public function test_using_the_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('App\ModuleExample\ModuleFacade'),
        );
    }

    public function test_starting_with_slash_and_using_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('\App\ModuleExample\ModuleFacade'),
        );
    }

    public function test_not_using_the_module_prefix_in_the_class(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('App\ModuleExample\Facade'),
        );
    }

    public function test_starting_with_slash_and_not_using_the_module_prefix_in_the_class(): void
    {
        self::assertSame(
            '\App\ModuleExample\Facade',
            GlobalKey::fromClassName('\App\ModuleExample\Facade'),
        );
    }

    public function test_dependency_provider_using_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\Provider',
            GlobalKey::fromClassName('\App\ModuleExample\ModuleProvider'),
        );
    }

    public function test_dependency_provider_not_using_module_prefix(): void
    {
        self::assertSame(
            '\App\ModuleExample\Provider',
            GlobalKey::fromClassName('\App\ModuleExample\Provider'),
        );
    }

    /**
     * The memo is only worth having if it is handed back untouched; a sentinel
     * that no derivation could ever produce is what tells a cache hit apart
     * from a silent recomputation of the very same string.
     */
    public function test_a_cached_key_is_returned_instead_of_being_recomputed(): void
    {
        $cache = new ReflectionProperty(GlobalKey::class, 'cache');
        /** @var array<string,string> $originalCache */
        $originalCache = $cache->getValue();

        $cache->setValue(null, [
            'GacelaTest\Sentinel\ModuleFacade' => '\only-reachable-from-the-cache',
        ] + $originalCache);

        try {
            self::assertSame(
                '\only-reachable-from-the-cache',
                GlobalKey::fromClassName('GacelaTest\Sentinel\ModuleFacade'),
            );
        } finally {
            $cache->setValue(null, $originalCache);
        }
    }
}
