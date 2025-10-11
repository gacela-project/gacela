<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithoutDependencies;

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

    public function test_with_prefix(): void
    {
        $facade = new WithPrefix\WithPrefixFacade();

        self::assertSame(
            ['Hello, Gacela from WithPrefix.'],
            $facade->greet('Gacela'),
        );
    }

    public function test_without_prefix(): void
    {
        $facade = new WithoutPrefix\Facade();

        self::assertSame(
            ['Hello, Gacela from WithoutPrefix.'],
            $facade->greet('Gacela'),
        );
    }
}
