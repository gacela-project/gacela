<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PhpBench\Attributes\AfterClassMethods;
use PhpBench\Attributes\BeforeClassMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

use function glob;
use function unlink;

/**
 * Full bootstrap + resolution of seven modules, with the file cache disabled
 * vs enabled. The cache directory is explicit and wiped around the run:
 * relying on the implicit default (sys_get_temp_dir()) made the bench pick up
 * merged-config cache files written by unrelated test runs on the same
 * machine, which poisoned the enabled-cache path with foreign config values.
 */
#[BeforeClassMethods('removeCacheFiles')]
#[AfterClassMethods('removeCacheFiles')]
#[Groups(['gate', 'bootstrap'])]
#[Revs(50)]
#[Iterations(5)]
final class FileCacheBench
{
    private const CACHE_DIR = __DIR__ . '/.gacela/cache';

    public static function removeCacheFiles(): void
    {
        foreach (glob(self::CACHE_DIR . '/*.php') ?: [] as $file) {
            unlink($file);
        }
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
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');

            if ($cacheEnabled) {
                $config->enableFileCache(self::CACHE_DIR);
            } else {
                $config->setFileCache(false);
            }

            $config->addBinding(StringValueInterface::class, new StringValue('testing-string'));

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

            $config->addSuffixTypeProvider('DepProvA');
            $config->addSuffixTypeProvider('DepProvB');
            $config->addSuffixTypeProvider('DepProvC');
            $config->addSuffixTypeProvider('DepProvD');
            $config->addSuffixTypeProvider('DepProvE');
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
        (new ModuleG\ModuleGFacade())->loadGacelaCacheFile();
    }
}
