<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerFactory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_non_factory_cached_seed(): void
    {
        $facade = new Module\Facade();

        self::assertSame(
            $facade->getCachedSeed(),
            $facade->getCachedSeed()
        );
    }

    public function test_factory_random_seed(): void
    {
        $facade = new Module\Facade();

        self::assertNotSame(
            $facade->getRandomSeed(),
            $facade->getRandomSeed()
        );
    }

    public function test_factory_different_objects(): void
    {
        $facade = new Module\Facade();

        self::assertNotSame(
            $facade->getRandomStringValue(),
            $facade->getRandomStringValue()
        );
    }
}
