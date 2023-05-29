<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules;

use Gacela\Console\Domain\AllAppModules\AppModule;
use PHPUnit\Framework\TestCase;

final class AppModuleTest extends TestCase
{
    public function test_from_class(): void
    {
        $actual = AppModule::fromClass(self::class);

        self::assertSame('AllAppModules', $actual->moduleName());
        self::assertSame(self::class, $actual->facadeClass());
    }
}
