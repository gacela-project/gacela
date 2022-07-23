<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\GacelaConfigAddAppConfigKeyValues;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\GacelaConfigAddAppConfigKeyValues\Module\Facade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('first_key', 'individual config key-value');

            $config->addAppConfigKeyValues([
                'some_key' => 'some value',
                'another_key' => 'another value',
                'override_key' => 'i am going to be overrided',
            ]);

            $config->addAppConfigKeyValue('override_key', 'truly override'); // it overrides previous 'override_key' key
        });
    }

    public function test_application_config_are_added_from_gacela_config(): void
    {
        $facade = new Facade();

        self::assertSame([
            'first_key' => 'individual config key-value',
            'some_key' => 'some value',
            'another_key' => 'another value',
            'override_key' => 'truly override',
        ], $facade->getConfigData());
    }
}
