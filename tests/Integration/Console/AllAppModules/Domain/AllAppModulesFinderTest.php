<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Config;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1DependencyProvider;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Facade;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Factory;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module2\Module2Facade;
use PHPUnit\Framework\TestCase;

use function array_slice;

final class AllAppModulesFinderTest extends TestCase
{
    private ConsoleFacade $consoleFacade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        $this->consoleFacade = new ConsoleFacade();
    }

    public function test_find_all_app_modules(): void
    {
        $actual = $this->consoleFacade->findAllAppModules();

        $expected = [
            new AppModule(
                join('\\', array_slice(explode('\\', Module1Facade::class), 0, -1)),
                'Module1',
                Module1Facade::class,
                Module1Factory::class,
                Module1Config::class,
                Module1DependencyProvider::class,
            ),
            new AppModule(
                join('\\', array_slice(explode('\\', Module2Facade::class), 0, -1)),
                'Module2',
                Module2Facade::class,
            ),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_find_some_app_modules(): void
    {
        $actual = $this->consoleFacade->findAllAppModules('Module1Facade');

        $expected = [
            new AppModule(
                join('\\', array_slice(explode('\\', Module1Facade::class), 0, -1)),
                'Module1',
                Module1Facade::class,
                Module1Factory::class,
                Module1Config::class,
                Module1DependencyProvider::class,
            ),
        ];

        self::assertEquals($expected, $actual);
    }
}
