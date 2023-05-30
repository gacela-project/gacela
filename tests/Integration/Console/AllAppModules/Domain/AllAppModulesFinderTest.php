<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\IntegrationAppModulesFacade1;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module2\IntegrationAppModulesFacade2;
use PHPUnit\Framework\TestCase;

final class AllAppModulesFinderTest extends TestCase
{
    private ConsoleFacade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        $this->facade = new ConsoleFacade();
    }

    public function test_find_all_app_modules(): void
    {
        $actual = $this->facade->findAllAppModules();

        $expected = [
            AppModule::fromClass(IntegrationAppModulesFacade1::class),
            AppModule::fromClass(IntegrationAppModulesFacade2::class),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_find_some_app_modules(): void
    {
        $actual = $this->facade->findAllAppModules('IntegrationAppModulesFacade1');

        $expected = [
            AppModule::fromClass(IntegrationAppModulesFacade1::class),
        ];

        self::assertEquals($expected, $actual);
    }
}
