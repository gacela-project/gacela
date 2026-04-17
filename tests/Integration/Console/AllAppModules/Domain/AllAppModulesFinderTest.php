<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Config;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Facade;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Factory;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Provider;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module2\Module2Facade;
use PHPUnit\Framework\TestCase;

use function array_slice;

final class AllAppModulesFinderTest extends TestCase
{
    private ConsoleFacade $consoleFacade;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        $this->consoleFacade = new ConsoleFacade();
    }

    public function test_find_all_app_modules(): void
    {
        $actual = $this->consoleFacade->findAllAppModules();

        $expected = [
            new AppModule(
                implode('\\', array_slice(explode('\\', Module1Facade::class), 0, -1)),
                'Module1',
                Module1Facade::class,
                Module1Factory::class,
                Module1Config::class,
                Module1Provider::class,
            ),
            new AppModule(
                implode('\\', array_slice(explode('\\', Module2Facade::class), 0, -1)),
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
                implode('\\', array_slice(explode('\\', Module1Facade::class), 0, -1)),
                'Module1',
                Module1Facade::class,
                Module1Factory::class,
                Module1Config::class,
                Module1Provider::class,
            ),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_app_module_paths_restricts_scan_to_configured_directories(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setAppModulePaths(['Module1']);
        });
        $this->consoleFacade = new ConsoleFacade();

        $actual = $this->consoleFacade->findAllAppModules();

        $expected = [
            new AppModule(
                implode('\\', array_slice(explode('\\', Module1Facade::class), 0, -1)),
                'Module1',
                Module1Facade::class,
                Module1Factory::class,
                Module1Config::class,
                Module1Provider::class,
            ),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_app_module_paths_accepts_absolute_paths(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setAppModulePaths([__DIR__ . '/Module2']);
        });
        $this->consoleFacade = new ConsoleFacade();

        $actual = $this->consoleFacade->findAllAppModules();

        $expected = [
            new AppModule(
                implode('\\', array_slice(explode('\\', Module2Facade::class), 0, -1)),
                'Module2',
                Module2Facade::class,
            ),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_app_module_paths_warns_and_skips_missing_directory(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setAppModulePaths(['does/not/exist', 'Module1']);
        });
        $this->consoleFacade = new ConsoleFacade();

        $capturedMessages = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$capturedMessages): bool {
            $capturedMessages[] = $errstr;
            return true;
        }, E_USER_WARNING);

        try {
            $actual = $this->consoleFacade->findAllAppModules();
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $actual);
        self::assertSame(Module1Facade::class, $actual[0]->facadeClass());
        self::assertCount(1, $capturedMessages);
        self::assertStringContainsString('does/not/exist', $capturedMessages[0]);
    }
}
