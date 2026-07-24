<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\AbstractFactory;
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
        DirectoryUtil::removeDir('.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ServiceModule');
        DirectoryUtil::removeDir('.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ProvidesModule');
        DirectoryUtil::removeDir('.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'MinimalModule');
        DirectoryUtil::removeDir('.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'MinimalTemplateModule');
        DirectoryUtil::removeDir('.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'MinimalRunModule');
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
> Path 'data/TestModule/{$fileName}Facade.php' created successfully
> Path 'data/TestModule/{$fileName}Factory.php' created successfully
> Path 'data/TestModule/{$fileName}Config.php' created successfully
> Path 'data/TestModule/{$fileName}Provider.php' created successfully
Module 'TestModule' created successfully
OUT;

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0) {
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

    public function test_make_module_with_service_template_generates_a_working_module(): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/ServiceModule --template=service --with-tests');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $display = $output->fetch();
        self::assertStringContainsString("Module 'ServiceModule' created successfully", $display);

        // Paths are built at runtime: the files only exist after the command ran.
        $moduleDir = getcwd() . '/data/ServiceModule';
        $files = [
            $moduleDir . '/ServiceModuleFacade.php',
            $moduleDir . '/ServiceModuleFactory.php',
            $moduleDir . '/ServiceModuleConfig.php',
            $moduleDir . '/ServiceModuleProvider.php',
            $moduleDir . '/Domain/ServiceModuleService.php',
            $moduleDir . '/Tests/ServiceModuleFacadeTest.php',
        ];
        foreach ($files as $file) {
            self::assertFileExists($file);
            // Every generated file must be valid PHP.
            exec(sprintf('php -l %s 2>&1', escapeshellarg($file)), $lintOutput, $lintExit);
            self::assertSame(0, $lintExit, implode("\n", $lintOutput));
        }

        // The generated module actually runs: the facade delegates through
        // the factory to the Domain service. The Psr4CodeGeneratorData
        // namespace is only mapped in the fixture composer.json, so load
        // the generated files explicitly.
        foreach ($files as $file) {
            if (!str_contains($file, '/Tests/')) {
                require_once $file;
            }
        }

        $facadeClass = 'Psr4CodeGeneratorData\ServiceModule\ServiceModuleFacade';
        self::assertTrue(class_exists($facadeClass));

        $execute = [new $facadeClass(), 'execute'];
        self::assertIsCallable($execute);
        self::assertSame('Hello from ServiceModuleService!', $execute());
    }

    public function test_make_module_provider_demonstrates_provides_attribute(): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/ProvidesModule');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $providerFile = getcwd() . '/data/ProvidesModule/ProvidesModuleProvider.php';
        self::assertFileExists($providerFile);

        $contents = (string)file_get_contents($providerFile);

        // Attribute-first: the scaffolded provider demonstrates a typed #[Provides]
        // method as the primary registration path.
        self::assertStringContainsString('use Gacela\Framework\Attribute\Provides;', $contents);
        self::assertStringContainsString('#[Provides(', $contents);
        self::assertStringContainsString('public function clock(): DateTimeImmutable', $contents);

        // ...while keeping the imperative provideModuleDependencies() available (no BC break).
        self::assertStringContainsString(
            'public function provideModuleDependencies(Container $container): void',
            $contents,
        );

        // The generated provider must be valid PHP.
        exec(sprintf('php -l %s 2>&1', escapeshellarg($providerFile)), $lintOutput, $lintExit);
        self::assertSame(0, $lintExit, implode("\n", $lintOutput));
    }

    public function test_make_module_minimal_generates_only_facade_and_factory(): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/MinimalModule --minimal');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $expectedOutput = <<<OUT
> Path 'data/MinimalModule/MinimalModuleFacade.php' created successfully
> Path 'data/MinimalModule/MinimalModuleFactory.php' created successfully
Module 'MinimalModule' created successfully
OUT;

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0) {
            $expectedOutput = str_replace("\n", PHP_EOL, $expectedOutput);
        }

        self::assertSame($expectedOutput, trim($output->fetch()));

        $moduleDir = getcwd() . '/data/MinimalModule';
        self::assertFileExists($moduleDir . '/MinimalModuleFacade.php');
        self::assertFileExists($moduleDir . '/MinimalModuleFactory.php');

        // Config and Provider are optional pillars: the minimal template omits them.
        self::assertFileDoesNotExist($moduleDir . '/MinimalModuleConfig.php');
        self::assertFileDoesNotExist($moduleDir . '/MinimalModuleProvider.php');
    }

    public function test_make_module_template_minimal_is_equivalent_to_the_flag(): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/MinimalTemplateModule --template=minimal');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $exitCode = $bootstrap->run($input, $output);

        self::assertSame(0, $exitCode);

        $moduleDir = getcwd() . '/data/MinimalTemplateModule';
        self::assertFileExists($moduleDir . '/MinimalTemplateModuleFacade.php');
        self::assertFileExists($moduleDir . '/MinimalTemplateModuleFactory.php');
        self::assertFileDoesNotExist($moduleDir . '/MinimalTemplateModuleConfig.php');
        self::assertFileDoesNotExist($moduleDir . '/MinimalTemplateModuleProvider.php');
    }

    public function test_make_module_minimal_module_resolves_and_runs_without_config_or_provider(): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/MinimalRunModule --minimal');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $moduleDir = getcwd() . '/data/MinimalRunModule';
        $facadeFile = $moduleDir . '/MinimalRunModuleFacade.php';
        $factoryFile = $moduleDir . '/MinimalRunModuleFactory.php';

        // Only the two pillars exist; no Config/Provider on disk.
        self::assertFileExists($facadeFile);
        self::assertFileExists($factoryFile);
        self::assertFileDoesNotExist($moduleDir . '/MinimalRunModuleConfig.php');
        self::assertFileDoesNotExist($moduleDir . '/MinimalRunModuleProvider.php');

        // Every generated file must be valid PHP.
        foreach ([$facadeFile, $factoryFile] as $file) {
            exec(sprintf('php -l %s 2>&1', escapeshellarg($file)), $lintOutput, $lintExit);
            self::assertSame(0, $lintExit, implode("\n", $lintOutput));
        }

        // The Psr4CodeGeneratorData namespace is only mapped in the fixture
        // composer.json, so load the generated files explicitly.
        require_once $facadeFile;
        require_once $factoryFile;

        $facadeClass = 'Psr4CodeGeneratorData\MinimalRunModule\MinimalRunModuleFacade';
        $factoryClass = 'Psr4CodeGeneratorData\MinimalRunModule\MinimalRunModuleFactory';
        self::assertTrue(class_exists($facadeClass));

        // The module resolves and runs: the Facade resolves its own generated
        // Factory through the framework with no Config or Provider present (no
        // exception thrown, and not the anonymous fallback factory).
        $factory = (new $facadeClass())->getFactory();
        self::assertInstanceOf(AbstractFactory::class, $factory);
        self::assertSame($factoryClass, $factory::class);
    }

    public function test_make_module_with_unknown_template_fails(): void
    {
        $input = new StringInput('make:module Psr4CodeGeneratorData/AnotherModule --template=nope');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $exitCode = $bootstrap->run($input, $output);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown template "nope"', $output->fetch());
    }
}
