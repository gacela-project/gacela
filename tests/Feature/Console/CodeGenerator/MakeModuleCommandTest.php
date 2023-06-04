<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MakeModuleCommandTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir('./data/TestModule');
    }

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        DirectoryUtil::removeDir('./data/TestModule');
    }

    /**
     * @dataProvider createModulesProvider
     */
    public function test_make_module(string $fileName, string $shortName): void
    {
        $input = new StringInput("make:module Psr4CodeGeneratorData/TestModule {$shortName}");
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expectedOutput = <<<OUT
> Path 'data/TestModule/{$fileName}Facade.php' created successfully
> Path 'data/TestModule/{$fileName}Factory.php' created successfully
> Path 'data/TestModule/{$fileName}Config.php' created successfully
> Path 'data/TestModule/{$fileName}DependencyProvider.php' created successfully
Module 'TestModule' created successfully
OUT;
        self::assertSame($expectedOutput, trim($output->fetch()));

        self::assertFileExists("./data/TestModule/{$fileName}Facade.php");
        self::assertFileExists("./data/TestModule/{$fileName}Factory.php");
        self::assertFileExists("./data/TestModule/{$fileName}Config.php");
        self::assertFileExists("./data/TestModule/{$fileName}DependencyProvider.php");
    }

    public function createModulesProvider(): iterable
    {
        yield 'module' => ['TestModule', ''];
        yield 'module -s' => ['', '-s'];
    }
}
