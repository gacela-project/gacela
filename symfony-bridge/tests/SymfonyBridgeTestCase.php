<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\SymfonyBridge\Fixtures\CountingService;
use PHPUnit\Framework\TestCase;

/**
 * Booting a kernel leaves process-global state behind: Gacela's singletons
 * and the fixture's construction counter. Every test class that boots one
 * must sweep all of it, so the sweeping lives here once -- the same shape as
 * the Laravel bridge's LaravelBridgeTestCase, minus the host statics Symfony
 * does not have.
 */
abstract class SymfonyBridgeTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        CountingService::$constructed = 0;
    }
}
