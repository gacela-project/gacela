<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain;

use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module2\IntegrationAppModulesFacade2;
use PHPUnit\Framework\TestCase;

final class AppModuleTest extends TestCase
{
    public function test_with_only_facade(): void
    {
        $actual = AppModule::fromClass(IntegrationAppModulesFacade2::class);

        self::assertSame('Module2', $actual->moduleName());
        self::assertSame(IntegrationAppModulesFacade2::class, $actual->facadeClass());
        self::assertNull($actual->factoryClass());
        self::assertNull($actual->configClass());
        self::assertNull($actual->dependencyProviderClass());
    }
}
