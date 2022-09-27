<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileProfiler;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @Revs(50)
 * @Iterations(2)
 */
final class FileProfilerBench
{
    private const TOTAL_LOADING_MODULES = 100;

    public function bench_with_profiler(): void
    {
        $this->gacelaBootstrapWithProfiler(true);
        $this->loadAllModules();
    }

    public function bench_without_profiler(): void
    {
        $this->gacelaBootstrapWithProfiler(false);
        $this->loadAllModules();
    }

    private function gacelaBootstrapWithProfiler(bool $withProfiler): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($withProfiler): void {
            $config->addAppConfig('config/*.php');
            $config->setProfilerEnabled($withProfiler);

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

        $this->removeCacheFile($withProfiler);
    }

    private function removeCacheFile(bool $withProfiler): void
    {
        // TODO: Clean this
        $filename = __DIR__ . '/' . '.gacela-class-names.cache';
        if (!$withProfiler && file_exists($filename)) {
            unlink($filename);
        }

        $filename = __DIR__ . '/' . '.gacela-custom-services.cache';
        if (!$withProfiler && file_exists($filename)) {
            unlink($filename);
        }
    }

    private function loadAllModules(): void
    {
        for ($i = 0; $i < self::TOTAL_LOADING_MODULES; ++$i) {
            (new ModuleA\Facade())->loadGacelaCacheFile();
            (new ModuleB\Facade())->loadGacelaCacheFile();
            (new ModuleC\Facade())->loadGacelaCacheFile();
            (new ModuleD\Facade())->loadGacelaCacheFile();
            (new ModuleE\Facade())->loadGacelaCacheFile();
            (new ModuleF\Facade())->loadGacelaCacheFile();
            (new ModuleG\Facade())->loadGacelaCacheFile();
        }
    }
}
