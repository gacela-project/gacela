<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GacelaTest extends TestCase
{
    public function test_null_get_cache_dir(): void
    {
        // Needed in order to set up the private `Gacela::$appRootDir as null` for this use case
        // when you try to access it before bootstrapping the application.
        $reflectionClass = new ReflectionClass(Gacela::class);
        $reflectionProperty = $reflectionClass->getProperty('appRootDir');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null);

        self::assertNull(Gacela::rootDir());
    }

    /**
     * @depends test_null_get_cache_dir
     */
    public function test_get_cache_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertEquals(__DIR__, Gacela::rootDir());
    }
}
