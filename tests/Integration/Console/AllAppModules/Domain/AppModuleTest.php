<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain;

use Gacela\Console\Domain\AllAppModules\AppModuleCreator;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Config;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1DependencyProvider;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Facade;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Factory;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module2\Module2Facade;
use PHPUnit\Framework\TestCase;

final class AppModuleTest extends TestCase
{
    private AppModuleCreator $appModuleCreator;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
        $this->appModuleCreator = new AppModuleCreator();
    }

    public function test_with_only_facade(): void
    {
        $actual = $this->appModuleCreator->fromClass(Module2Facade::class);

        self::assertSame('Module2', $actual->moduleName());
        self::assertSame(Module2Facade::class, $actual->facadeClass());
        self::assertNull($actual->factoryClass());
        self::assertNull($actual->configClass());
        self::assertNull($actual->dependencyProviderClass());
    }

    public function test_full_module(): void
    {
        $actual = $this->appModuleCreator->fromClass(Module1Facade::class);

        self::assertSame('Module1', $actual->moduleName());
        self::assertSame(Module1Facade::class, $actual->facadeClass());
        self::assertSame(Module1Factory::class, $actual->factoryClass());
        self::assertSame(Module1Config::class, $actual->configClass());
        self::assertSame(Module1DependencyProvider::class, $actual->dependencyProviderClass());
    }
}
