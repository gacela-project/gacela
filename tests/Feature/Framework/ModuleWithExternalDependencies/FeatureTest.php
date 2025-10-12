<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    /**
     * A module (ModuleWithDependencies\Facade) with one module-dependency (DependentModule).
     */
    public function test_with_a_dependency(): void
    {
        $facade = new Supplier\Facade();

        self::assertSame(
            [
                'Hello, Gacela from Supplier.',
                'Hello, Gacela from Dependent.',
            ],
            $facade->greet('Gacela'),
        );
    }
}
