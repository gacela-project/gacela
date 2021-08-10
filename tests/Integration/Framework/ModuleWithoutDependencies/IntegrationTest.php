<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithoutDependencies;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_with_prefix(): void
    {
        $facade = new WithPrefix\WithPrefixFacade();

        self::assertEquals(
            ['Hello, Gacela from WithPrefix.'],
            $facade->greet('Gacela')
        );
    }

    public function test_without_prefix(): void
    {
        $facade = new WithoutPrefix\Facade();

        self::assertEquals(
            ['Hello, Gacela from WithoutPrefix.'],
            $facade->greet('Gacela')
        );
    }
}
