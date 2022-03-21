<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA\ConfModuleA;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA\DepProModuleA;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA\FactoryModuleA;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB\ConfModuleB;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB\DepProModuleB;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB\FactoryModuleB;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleC\Config;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleC\DependencyProvider;
use GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleC\Factory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_cache_files(): void
    {
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleB\FacadeModuleB())->loadGacelaCacheFile();
        (new ModuleC\Facade())->loadGacelaCacheFile();

        $cacheFileContent = file_get_contents(self::getGacelaCacheFileName());
        $actual = json_decode($cacheFileContent, true, 512, JSON_THROW_ON_ERROR);

        $expected = [
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA\Factory' => '\\' . FactoryModuleA::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA\Config' => '\\' . ConfModuleA::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA\DependencyProvider' => '\\' . DepProModuleA::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB\Factory' => '\\' . FactoryModuleB::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB\Config' => '\\' . ConfModuleB::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB\DependencyProvider' => '\\' . DepProModuleB::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleC\Factory' => '\\' . Factory::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleC\Config' => '\\' . Config::class,
            '\GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleC\DependencyProvider' => '\\' . DependencyProvider::class,
        ];

        self::assertSame($expected, $actual);
    }

    private static function getGacelaCacheFileName(): string
    {
        return __DIR__ . '/' . AbstractClassResolver::GACELA_CACHE_JSON_FILE;
    }
}
