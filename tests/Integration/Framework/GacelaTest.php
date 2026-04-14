<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GacelaTest extends TestCase
{
    public function test_exception_if_non_bootstrapped(): void
    {
        // Needed in order to set up the private `Gacela::$appRootDir as null` for this use case
        // when you try to access it before bootstrapping the application.
        $gacelaProxy = new ReflectionClass(Gacela::class);
        $appRootDirProp = $gacelaProxy->getProperty('appRootDir');
        $appRootDirProp->setValue($gacelaProxy, value: null);

        $this->expectException(GacelaNotBootstrappedException::class);
        $this->expectExceptionMessage(GacelaNotBootstrappedException::MESSAGE);

        Gacela::rootDir();
    }

    public function test_get_cache_dir(): void
    {
        Gacela::bootstrap('any/root/directory', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertSame('any/root/directory', Gacela::rootDir());
    }

    public function test_container_throws_when_not_bootstrapped(): void
    {
        $gacelaProxy = new ReflectionClass(Gacela::class);
        $mainContainerProp = $gacelaProxy->getProperty('mainContainer');
        $mainContainerProp->setValue($gacelaProxy, value: null);

        $this->expectException(GacelaNotBootstrappedException::class);

        Gacela::container();
    }

    public function test_container_returns_the_main_container_when_bootstrapped(): void
    {
        Gacela::bootstrap('any/root/directory', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $gacelaProxy = new ReflectionClass(Gacela::class);
        $mainContainerProp = $gacelaProxy->getProperty('mainContainer');
        $mainContainerProp->setValue($gacelaProxy, value: new Container());

        self::assertInstanceOf(Container::class, Gacela::container());
    }

    public function test_add_global_with_empty_context_resolves_caller_file_as_context(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $service = new class() extends AbstractConfig {};

        Gacela::addGlobal($service);

        // The caller of addGlobal() is this test file, so the resolved
        // context should be this file's basename (without `.php`).
        self::assertSame(
            $service,
            AnonymousGlobal::getByKey(AnonymousGlobal::createCacheKey('GacelaTest', 'Config')),
        );
    }

    public function test_add_global_with_file_path_context_uses_its_basename(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $service = new class() extends AbstractConfig {};

        Gacela::addGlobal($service, __FILE__);

        self::assertSame(
            $service,
            AnonymousGlobal::getByKey(AnonymousGlobal::createCacheKey('GacelaTest', 'Config')),
        );
    }
}
