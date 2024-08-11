<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function sprintf;

final class MakeModuleCommandTest extends TestCase
{
    private const CACHE_DIR = '.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'TestModule';

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    #[DataProvider('createModulesProvider')]
    public function test_make_module(string $fileName, string $shortName): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/TestModule ' . $shortName);
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

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0) {
            $expectedOutput = str_replace("\n", PHP_EOL, $expectedOutput);
        }

        self::assertSame($expectedOutput, trim($output->fetch()));

        self::assertFileExists(sprintf('./data/TestModule/%sFacade.php', $fileName));
        self::assertFileExists(sprintf('./data/TestModule/%sFactory.php', $fileName));
        self::assertFileExists(sprintf('./data/TestModule/%sConfig.php', $fileName));
        self::assertFileExists(sprintf('./data/TestModule/%sDependencyProvider.php', $fileName));
    }

    public static function createModulesProvider(): iterable
    {
        yield 'module' => ['TestModule', ''];
        yield 'module -s' => ['', '-s'];
    }
}
