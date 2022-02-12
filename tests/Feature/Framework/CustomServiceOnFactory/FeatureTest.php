<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFactory;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\CustomServiceOnFactory\CustomModule\Facade;
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
                'from-config' => 1,
                'from-factory' => 1,
            ],
            $facade->findAllKeyValuesUsingRepository()
        );
    }
}
