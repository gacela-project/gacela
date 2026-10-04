<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\PillarSuffixedService;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ServiceResolver\PillarSuffixedService\Product\PriceConfig;
use GacelaTest\Integration\Framework\ServiceResolver\PillarSuffixedService\Product\ProductConfig;
use GacelaTest\Integration\Framework\ServiceResolver\PillarSuffixedService\Product\ProductFactory;
use PHPUnit\Framework\TestCase;

/**
 * A mapped service whose name ends in a pillar suffix is not that pillar, and
 * must not share the cache entry of the module's own pillar.
 */
final class PillarSuffixedServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    public function test_the_pillar_resolved_first_does_not_answer_for_the_service(): void
    {
        $factory = new ProductFactory();

        self::assertInstanceOf(ProductConfig::class, $factory->config());
        self::assertInstanceOf(PriceConfig::class, $factory->getPriceConfig());
    }

    public function test_the_service_resolved_first_does_not_answer_for_the_pillar(): void
    {
        $factory = new ProductFactory();

        self::assertInstanceOf(PriceConfig::class, $factory->getPriceConfig());
        self::assertInstanceOf(ProductConfig::class, $factory->config());
    }
}
