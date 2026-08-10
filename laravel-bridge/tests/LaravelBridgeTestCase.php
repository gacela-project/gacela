<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\LaravelBridge\Fixtures\CountingService;
use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Booting a provider leaves process-global state behind: Gacela's own
 * singletons, and Laravel's -- `Artisan::starting()` closures and the
 * optimize registries are static. Every test class that boots one must sweep
 * all of it, so the sweeping lives here rather than in whichever class first
 * failed under an unlucky seed.
 */
abstract class LaravelBridgeTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        CountingService::$constructed = 0;
        Artisan::forgetBootstrappers();
        ServiceProvider::$optimizeCommands = [];
        ServiceProvider::$optimizeClearCommands = [];
    }
}
