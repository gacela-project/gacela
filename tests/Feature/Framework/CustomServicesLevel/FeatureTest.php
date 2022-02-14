<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Facade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    private Facade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            'custom-services-location' => [
                'Level1\Level2\Level3\Level4',
            ],
        ]);
        $this->facade = new Facade();
    }

    public function test_custom_service_which_uses_config_from_module(): void
    {
        self::assertSame(
            'Hi, Gacela! From level 4 (config-value)',
            $this->facade->greet('Gacela')
        );
    }
}
