<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithoutDependencies;

use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function testWithPrefix(): void
    {
        $facade = new WithPrefix\WithPrefixFacade();

        self::assertEquals(
            ['Hello, Gacela from WithPrefix.'],
            $facade->greet('Gacela')
        );
    }

    public function testWithoutPrefix(): void
    {
        $facade = new WithoutPrefix\Facade();

        self::assertEquals(
            ['Hello, Gacela from WithoutPrefix.'],
            $facade->greet('Gacela')
        );
    }
}
