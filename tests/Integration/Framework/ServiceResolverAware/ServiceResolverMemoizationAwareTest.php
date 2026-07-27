<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolverAware;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ServiceResolverMemoizationAwareTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_an_already_resolved_service_survives_a_cache_reset(): void
    {
        $dummy = new DummyAttributeServiceResolverAware();
        $firstRepository = $dummy->getRepository();

        // After the reset Gacela is no longer bootstrapped, so a second
        // resolution would blow up: only the memoized service can answer.
        Gacela::resetCache();

        self::assertSame($firstRepository, $dummy->getRepository());
    }
}
