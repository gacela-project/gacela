<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ClassResolver\Provider\ModernModule\Factory as ModernFactory;
use GacelaTest\Integration\Framework\ClassResolver\Provider\ModernModule\Provider;
use PHPUnit\Framework\TestCase;

final class ProviderRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetGacela();
        Provider::resetCallCount();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        $this->resetGacela();
        Provider::resetCallCount();
    }

    /**
     * A provider body is not required to be idempotent — it may increment counters,
     * register with external systems, or log. Registering it twice runs those side
     * effects twice, which 1.20 shipped as a bug when two resolvers shared a cache
     * slot. 2.0 resolves through a single `ProviderResolver`, so the duplicate path
     * is gone; this pins the property rather than the mechanism that once broke it.
     */
    public function test_a_provider_is_registered_exactly_once(): void
    {
        (new ModernFactory())->getGreeting();

        self::assertSame(1, Provider::$provideCallCount);
    }

    public function test_a_provider_provides_its_dependencies(): void
    {
        self::assertSame('hello from the modern provider', (new ModernFactory())->getGreeting());
    }

    private function resetGacela(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        InMemoryCache::resetCache();
    }
}
