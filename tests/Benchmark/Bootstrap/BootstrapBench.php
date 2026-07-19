<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PhpBench\Attributes\AfterClassMethods;
use PhpBench\Attributes\BeforeClassMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

use function glob;
use function unlink;

/**
 * Gacela::bootstrap() itself, isolated from module resolution — the dominant
 * cost of real-app startup. Cold boots re-read the config files every rev;
 * warm boots load the merged-config file cache written by the first boot.
 */
#[BeforeClassMethods('removeCacheFiles')]
#[AfterClassMethods('removeCacheFiles')]
#[Groups(['gate', 'bootstrap'])]
#[Revs(20)]
#[Iterations(5)]
final class BootstrapBench
{
    private const CACHE_DIR = __DIR__ . '/.gacela/cache';

    public static function removeCacheFiles(): void
    {
        foreach (glob(self::CACHE_DIR . '/*.php') ?: [] as $file) {
            unlink($file);
        }
    }

    public function warmMergedConfigCache(): void
    {
        self::removeCacheFiles();
        $this->bootstrapWarm();
    }

    /**
     * Renamed from bench_bootstrap_cold when Gacela::resetCache() started
     * clearing the glob cache (#474): the old numbers measured bootstraps
     * that reused a stale glob file list, so they are not comparable — a
     * cold boot now genuinely re-scans the config files every rev.
     */
    public function bench_bootstrap_cold_rescan(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
            $config->setFileCache(false);
        });
    }

    #[BeforeMethods('warmMergedConfigCache')]
    public function bench_bootstrap_warm(): void
    {
        $this->bootstrapWarm();
    }

    private function bootstrapWarm(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
            $config->enableFileCache(self::CACHE_DIR);
        });
    }
}
