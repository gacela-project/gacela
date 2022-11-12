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

    public function test_factory_create_different_objects(): void
    {
        $facade = new Module\Facade();

        self::assertNotEquals(
            $facade->getRandomStringValue(),
            $facade->getRandomStringValue()
        );
    }
}
