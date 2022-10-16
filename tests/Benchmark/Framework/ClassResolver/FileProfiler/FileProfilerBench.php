<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileProfiler;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Profiler\ClassNameJsonProfiler;
use Gacela\Framework\ClassResolver\Profiler\CustomServicesJsonProfiler;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @Revs(10)
 * @Iterations(2)
 * @BeforeClassMethods("removeFiles")
 */
final class FileProfilerBench
{
    public static function removeFiles(): void
    {
        $removeFile = static function (string $filename): void {
            $filenameFullPath = __DIR__ . '/.gacela/profiler/' . $filename;
            if (file_exists($filenameFullPath)) {
                unlink($filenameFullPath);
            }
        };
        $removeFile(ClassNameJsonProfiler::FILENAME);
        $removeFile(CustomServicesJsonProfiler::FILENAME);
    }

    public function bench_profiler(): void
    {
        $this->gacelaBootstrapWithProfiler();
        $this->loadAllModules();
    }

    private function gacelaBootstrapWithProfiler(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php');
            $config->setProfilerEnabled(true);

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
