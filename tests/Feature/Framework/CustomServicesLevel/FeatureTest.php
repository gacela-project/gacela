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
            // The order is relevant for the priority in case the same
            // class name is found in both. The first found will be used.
            'custom-services-location' => [
                'Level1\Level2\Level3\Level4',
            ],
        ]);
        $this->facade = new Facade();
    }

    public function test_using_custom_services_from_factory(): void
    {
        self::assertSame(
            'Hi, Gacela! config-ok',
            $this->facade->greet('Gacela')
        );
    }
}
