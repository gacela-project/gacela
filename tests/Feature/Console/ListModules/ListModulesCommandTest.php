<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ListModulesCommandTest extends TestCase
{
    public function test_list_modules_simple(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $input = new StringInput('list:modules --simple');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expected = <<<TXT
1.- TestModule3
2.- TestModule1
3.- TestModule2

TXT;
        self::assertSame($expected, $output->fetch());
    }

    public function test_list_modules(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $input = new StringInput('list:modules');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expected = <<<TXT
============================
1.- TestModule3
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Facade
Factory: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Factory
Config: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Config
DependencyProvider: None
============================
2.- TestModule1
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Facade
Factory: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Factory
Config: None
DependencyProvider: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1DependencyProvider
============================
3.- TestModule2
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule2\TestModule2Facade
Factory: None
Config: None
DependencyProvider: None

TXT;
        self::assertSame($expected, $output->fetch());
    }

    /**
     * @dataProvider commandInputProvider
     */
    public function test_list_modules_with_filter(string $input): void
    {
        Gacela::bootstrap(__DIR__);

        $input = new StringInput('list:modules' . $input);
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $out = $output->fetch();

        self::assertStringContainsString('TestModule1', $out);
        self::assertStringNotContainsString('TestModule2', $out);
        self::assertStringNotContainsString('TestModule3', $out);
        self::assertStringNotContainsString('vendor', $out);
        self::assertStringNotContainsString('ToBeIgnored', $out);
    }

    public function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\\TestModule1'];
    }
}
