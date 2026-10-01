<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
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
 * Config::init() — building the merged configuration. Cold globs and parses
 * the config files on every call; warm loads the persisted merged-config
 * file cache instead.
 */
#[BeforeClassMethods('removeCacheFiles')]
#[AfterClassMethods('removeCacheFiles')]
#[Groups(['gate', 'config'])]
#[Revs(50)]
#[Iterations(5)]
final class ConfigInitBench
{
    private const APP_ROOT = __DIR__ . '/ConfigInit';

    private const CACHE_DIR = self::APP_ROOT . '/.gacela/cache';

    public static function removeCacheFiles(): void
    {
        foreach (glob(self::CACHE_DIR . '/*.php') ?: [] as $file) {
            unlink($file);
        }
    }

    public function setUpCold(): void
    {
        Gacela::bootstrap(self::APP_ROOT, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
            $config->setFileCache(false);
        });
    }

    public function setUpWarm(): void
    {
        self::removeCacheFiles();

        Gacela::bootstrap(self::APP_ROOT, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
            $config->enableFileCache(self::CACHE_DIR);
        });

        // The deployed state: `cache:warm` wrote the merged config cache, and
        // the measured revs read it without looking at the config files.
        Config::getInstance()->writeMergedConfigCache();
    }

    public function setUpAutoWarmed(): void
    {
        self::removeCacheFiles();

        Gacela::bootstrap(self::APP_ROOT, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
            $config->enableFileCache(self::CACHE_DIR);
        });

        // No `cache:warm`: the first init writes the cache, so every measured
        // rev checks the config files have not changed since.
        Config::getInstance()->init();
    }

    #[BeforeMethods('setUpCold')]
    public function bench_config_init_cold(): void
    {
        Config::getInstance()->init();
    }

    #[BeforeMethods('setUpWarm')]
    public function bench_config_init_warm(): void
    {
        Config::getInstance()->init();
    }

    #[BeforeMethods('setUpAutoWarmed')]
    public function bench_config_init_auto_warmed(): void
    {
        Config::getInstance()->init();
    }
}
