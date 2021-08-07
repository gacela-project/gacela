<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithExternalDependencies;

use Gacela\Framework\Config;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Config::getInstance()->init(__DIR__);
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
