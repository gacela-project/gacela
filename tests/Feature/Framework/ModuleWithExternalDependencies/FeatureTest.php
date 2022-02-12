<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * A module (ModuleWithDependencies\Facade) with one module-dependency (DependentModule).
     */
    public function test_with_a_dependency(): void
    {
        $facade = new Supplier\Facade();

        self::assertEquals(
            [
                'Hello, Gacela from Supplier.',
                'Hello, Gacela from Dependent.',
            ],
            $facade->greet('Gacela')
        );
    }
}
