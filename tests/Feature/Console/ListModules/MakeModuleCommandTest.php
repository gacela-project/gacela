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

        $input = new StringInput('list:modules');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $outputText = $output->fetch();

        self::assertStringContainsString(
            'TestModule1Facade | GacelaTest\Feature\Console\ListModules\TestModule1',
            $outputText,
        );
        self::assertStringContainsString(
            'TestModule2Facade | GacelaTest\Feature\Console\ListModules\TestModule2',
            $outputText,
        );
        self::assertStringContainsString(
            'TestModule3Facade | GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3',
            $outputText,
        );
    }
}
