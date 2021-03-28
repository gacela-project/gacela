<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithExternalDependencies;

use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    /**
     * A module (ModuleWithDependencies\Facade) with one module-dependency (DependentModule).
     */
    public function testExampleB(): void
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
