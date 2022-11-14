<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendService;

use ArrayObject;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ExtendService\Module\DependencyProvider;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    private Module\Facade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();

            $config->extendService(
                DependencyProvider::ARRAY_AS_OBJECT,
                static function (ArrayObject $arrayObject): void {
                    $arrayObject->append(3);
                }
            );

            $config->extendService(
                DependencyProvider::ARRAY_FROM_FUNCTION,
                static function (ArrayObject $arrayObject): void {
                    $arrayObject->append(4);
                }
            );
        });

        $this->facade = new Module\Facade();
    }

    public function test_extend_service_as_object(): void
    {
        self::assertEquals(
            new ArrayObject([1, 2, 3]),
            $this->facade->getArrayAsObject()
        );
    }

    public function test_extend_service_from_function(): void
    {
        self::assertEquals(
            new ArrayObject([1, 2, 4]),
            $this->facade->getArrayFromFunction()
        );
    }
}
