<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework;

use Gacela\Framework\Bootstrap\GacelaConfig;
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
        $appRootDirProp->setAccessible(true);
        $appRootDirProp->setValue(null);

        $this->expectException(GacelaNotBootstrappedException::class);

        Gacela::rootDir();
    }

    public function test_get_cache_dir(): void
    {
        Gacela::bootstrap('any/root/directory', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertEquals('any/root/directory', Gacela::rootDir());
    }
}
