<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFacade;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule\Facade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            // The order is relevant for the priority in case the same
            // class name is found in both. The first found will be used.
            'custom-service-paths' => [
                'Application',
                'Infrastructure',
            ],
        ]);
    }

    public function test_load_custom_service(): void
    {
        $facade = new Facade();

        self::assertSame(
            [
                'from-application-repository' => [
                    'from-config' => 1,
                    'from-application-factory' => 2,
                ],
                'from-infrastructure-entity-manager' => [
                    'from-config' => 1,
                    'from-infrastructure-factory' => 3,
                ],
            ],
            $facade->findAllKeyValuesUsingRepository()
        );
    }
}
