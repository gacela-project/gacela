<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MakeModuleCommandTest extends TestCase
{
    public function test_list_modules(): void
    {
        Gacela::bootstrap(__DIR__);
        $input = new StringInput("list:modules");
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expectedOutput = <<<OUT
Modules found:
- TestModule1 | GacelaTest\Feature\Console\ListModules\Modules\TestModule1
- TestModule2 | GacelaTest\Feature\Console\ListModules\Modules\TestModule2
- TestModule3 | GacelaTest\Feature\Console\ListModules\Modules\LevelUp\TestModule3
OUT;
        self::assertSame($expectedOutput, trim($output->fetch()));
    }
}
