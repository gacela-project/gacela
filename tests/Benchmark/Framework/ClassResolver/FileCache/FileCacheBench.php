<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @Revs(150)
 * @Iterations(10)
 * BeforeClassMethods("removeFiles")
 */
final class FileCacheBench
{
    public static function removeFiles(): void
    {
        $removeFile = static function (string $filename): void {
            $filenameFullPath = __DIR__ . '/.gacela/cache/' . $filename;
            if (file_exists($filenameFullPath)) {
                unlink($filenameFullPath);
            }
        };
        $removeFile(ClassNamePhpCache::FILENAME);
        $removeFile(CustomServicesPhpCache::FILENAME);
    }

    public function bench_without_cache(): void
    {
        $this->gacelaBootstrapWithCache(false);
        $this->loadAllModules();
    }

    public function bench_with_cache(): void
    {
        $this->gacelaBootstrapWithCache(true);
        $this->loadAllModules();
    }

    private function gacelaBootstrapWithCache(bool $cacheEnabled): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheEnabled): void {
            $config->addAppConfig('config/*.php');
            $config->setFileCacheEnabled($cacheEnabled);

            $config->addMappingInterface(StringValueInterface::class, new StringValue('testing-string'));

            $config->addSuffixTypeFactory('FactoryA');
            $config->addSuffixTypeFactory('FactoryB');
            $config->addSuffixTypeFactory('FactoryC');
            $config->addSuffixTypeFactory('FactoryD');
            $config->addSuffixTypeFactory('FactoryE');

            $config->addSuffixTypeConfig('ConfigA');
            $config->addSuffixTypeConfig('ConfigB');
            $config->addSuffixTypeConfig('ConfigC');
            $config->addSuffixTypeConfig('ConfigD');
            $config->addSuffixTypeConfig('ConfigE');

            $config->addSuffixTypeDependencyProvider('DepProvA');
            $config->addSuffixTypeDependencyProvider('DepProvB');
            $config->addSuffixTypeDependencyProvider('DepProvC');
            $config->addSuffixTypeDependencyProvider('DepProvD');
            $config->addSuffixTypeDependencyProvider('DepProvE');
        });
    }

    private function loadAllModules(): void
    {
        (new ModuleA\Facade())->loadGacelaCacheFile();
        (new ModuleB\Facade())->loadGacelaCacheFile();
        (new ModuleC\Facade())->loadGacelaCacheFile();
        (new ModuleD\Facade())->loadGacelaCacheFile();
        (new ModuleE\Facade())->loadGacelaCacheFile();
        (new ModuleF\Facade())->loadGacelaCacheFile();
        (new ModuleG\Facade())->loadGacelaCacheFile();
    }
}
