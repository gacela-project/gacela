<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FlexibleServiceOnFactory;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\FlexibleServiceOnFactory\FlexibleApiModule\Facade;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            'flexible-services' => [
                'paths' => ['Infrastructure'],
                'resolvable-types' => ['Repository'],
            ],
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
