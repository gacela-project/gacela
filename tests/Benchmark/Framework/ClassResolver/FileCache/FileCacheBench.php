<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @Revs(50)
 * @Iterations(2)
 */
final class FileCacheBench
{
    public function bench_with_cache(): void
    {
        $this->gacelaBootstrapWithCache(true);
        $this->loadAllModules();
    }

    public function bench_without_cache(): void
    {
        $this->gacelaBootstrapWithCache(false);
        $this->loadAllModules();
    }

    private function gacelaBootstrapWithCache(bool $withCache): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($withCache): void {
            $config->addAppConfig('config/*.php');
            $config->setCacheEnabled($withCache);

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

        $this->removeCacheFile($withCache);
    }

    private function removeCacheFile(bool $withCache): void
    {
        $cacheFilename = __DIR__ . '/' . '.gacela-class-names.cache';
        if (!$withCache && file_exists($cacheFilename)) {
            unlink($cacheFilename);
        }
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
