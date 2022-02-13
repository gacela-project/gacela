<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceNotValidException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Facade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            // The order is relevant for the priority in case the same
            // class name is found in both. The first found will be used.
            'custom-services-location' => [
                'Application',
                'Infrastructure\Persistence',
            ],
        ]);
    }

    public function test_using_custom_services_from_facade(): void
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
            $facade->usingCustomServicesFromFacade()
        );
    }

    public function test_using_custom_services_from_factory(): void
    {
        $facade = new Facade();

        self::assertSame(
            [
                'from-application-repository' => [
                    'from-config' => 1,
                    'from-application-factory' => 2,
                ],
            ],
            $facade->usingCustomServicesFromFactory()
        );
    }

    public function test_custom_service_which_does_not_extends_abstract_custom_service(): void
    {
        $this->expectException(CustomServiceNotValidException::class);
        $this->expectErrorMessageMatches('~"Greeter".*"CustomModule".*AbstractCustomService~');
        $facade = new Facade();
        $facade->greetUsingPlainCustomService('Gacela');
    }
}
