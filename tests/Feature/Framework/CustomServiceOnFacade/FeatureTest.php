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
            'custom-service-paths' => ['Infrastructure'],
        ]);
    }

    public function test_load_custom_service(): void
    {
        $facade = new Facade();

        self::assertSame(
            [
                'from-repository' => [
                    'from-config' => 1,
                    'from-factory' => 1,
                ],
                'from-entity-manager' => [
                    'from-config' => 1,
                    'from-factory' => 1,
                ],
            ],
            $facade->findAllKeyValuesUsingRepository()
        );
    }
}
