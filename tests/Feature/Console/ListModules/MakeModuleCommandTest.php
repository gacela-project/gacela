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

        $out = $output->fetch();

        self::assertMatchesRegularExpression('#TestModule1.*ListModules\\\TestModule1#', $out);
        self::assertMatchesRegularExpression('#TestModule2.*ListModules\\\TestModule2#', $out);
        self::assertMatchesRegularExpression('#TestModule3.*ListModules\\\LevelUp\\\TestModule3#', $out);
        self::assertStringNotContainsString('vendor', $out);
        self::assertStringNotContainsString('ToBeIgnored', $out);
    }
}
