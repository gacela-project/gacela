<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class AllAppModulesFinderTest extends TestCase
{
    public function test_find_all_app_modules(): void
    {
        Gacela::bootstrap(__DIR__);

        $facade = new ConsoleFacade();
        $actual = $facade->findAllAppModules();

        $expected = [
            new AppModule(
                'IntegrationAppModulesFacade1',
                'GacelaTest\Integration\Console\AllAppModules\Domain\Namespace1\Level1',
            ),
            new AppModule(
                'IntegrationAppModulesFacade2',
                'GacelaTest\Integration\Console\AllAppModules\Domain\Namespace1\Level1',
            ),
        ];

        self::assertEquals($expected, $actual);
    }
}
