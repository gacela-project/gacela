<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ListModulesCommandTest extends TestCase
{
    public function test_list_modules(): void
    {
        Gacela::bootstrap(__DIR__);

        $input = new StringInput('list:modules');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expected = <<<TXT
==============
TestModule3
--------------
Facade: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Facade
Factory: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Factory
Config: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Config
DependencyProvider: None
==============
TestModule1
--------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Facade
Factory: None
Config: None
DependencyProvider: None
==============
TestModule2
--------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule2Facade
Factory: None
Config: None
DependencyProvider: None

TXT;
        self::assertSame($expected, $output->fetch());
    }
}
