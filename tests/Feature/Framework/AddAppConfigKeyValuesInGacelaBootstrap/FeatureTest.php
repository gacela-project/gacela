<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AddAppConfigKeyValuesInGacelaBootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\GacelaCache;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\AddAppConfigKeyValuesInGacelaBootstrap\Module\Facade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValues([
                GacelaCache::KEY_ENABLED => true,
                'some_key' => 'some value',
                'another_key' => 'another value',
            ]);
            $config->addAppConfigKeyValue(GacelaCache::KEY_ENABLED, false); // it overrides previous 'GacelaCache::KEY_ENABLED' key
        });
    }

    public function test_override_factory_from_highest_prio_namespace(): void
    {
        $facade = new Facade();

        self::assertSame([
            GacelaCache::KEY_ENABLED => false,
            'some_key' => 'some value',
            'another_key' => 'another value',
        ], $facade->getConfigData());
    }
}
