<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
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
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    public function test_make_module_command_description(): void
    {
        $bootstrap = new ConsoleBootstrap();
        $command = $bootstrap->find('make:module');

        $description = $command->getDescription();

        // Test that the description contains 'Generate a basic module with an empty ' followed by the expected filenames
        self::assertStringContainsString('Generate a basic module with an empty ', $description);
        self::assertStringContainsString('Facade', $description);
        self::assertStringContainsString('Factory', $description);
        self::assertStringContainsString('Config', $description);
        self::assertStringContainsString('Provider', $description);

        // Ensure it's in the correct order (not reversed or partial)
        self::assertStringStartsWith('Generate a basic module with an empty ', $description);
    }

    #[DataProvider('createModulesProvider')]
    public function test_make_module(string $fileName, bool $shortName): void
    {
        $shortNameFlag = $shortName ? '--short-name' : '';
        $input = new StringInput('make:module Psr4CodeGeneratorData/TestModule ' . $shortNameFlag);
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expectedOutput = <<<OUT
Generating module with template: basic
> Path 'data/TestModule/{$fileName}Facade.php' created successfully
> Path 'data/TestModule/{$fileName}Factory.php' created successfully
> Path 'data/TestModule/{$fileName}Config.php' created successfully
> Path 'data/TestModule/{$fileName}Provider.php' created successfully
Module 'TestModule' created successfully
OUT;

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0) {
            $expectedOutput = str_replace("\n", PHP_EOL, $expectedOutput);
        }

        self::assertSame($expectedOutput, trim($output->fetch()));

        self::assertFileExists(sprintf('./data/TestModule/%sFacade.php', $fileName));
        self::assertFileExists(sprintf('./data/TestModule/%sFactory.php', $fileName));
        self::assertFileExists(sprintf('./data/TestModule/%sConfig.php', $fileName));
        self::assertFileExists(sprintf('./data/TestModule/%sProvider.php', $fileName));
    }

    public static function createModulesProvider(): iterable
    {
        yield 'module' => ['TestModule', false];
        yield 'module -s' => ['', true];
    }
}
