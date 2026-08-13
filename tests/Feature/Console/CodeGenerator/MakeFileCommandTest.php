<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function sprintf;

final class MakeFileCommandTest extends TestCase
{
    private const CACHE_DIR = '.' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestModule';

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

    public function test_make_file_command_description(): void
    {
        $bootstrap = new ConsoleBootstrap();
        $command = $bootstrap->find('make:file');

        $description = $command->getDescription();

        self::assertStringContainsString('Generate a ', $description);
        self::assertStringContainsString('Facade', $description);
        self::assertStringContainsString('Factory', $description);
        self::assertStringContainsString('Config', $description);
        self::assertStringContainsString('Provider', $description);

        self::assertStringStartsWith('Generate a ', $description);
    }

    #[DataProvider('createFilesProvider')]
    public function test_make_file(string $action, string $fileName, bool $shortName): void
    {
        $shortNameFlag = $shortName ? '--short-name' : '';
        $input = new StringInput(sprintf('make:file Psr4CodeGenerator/TestModule %s %s', $action, $shortNameFlag));
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        self::assertSame(sprintf("> Path 'src/TestModule/%s.php' created successfully", $fileName), trim($output->fetch()));
        self::assertFileExists(sprintf('./src/TestModule/%s.php', $fileName));
    }

    /**
     * The same rule `make:module` applies, and for the same reason: every
     * segment becomes a namespace and a class name, so generating from one that
     * is not a PHP label writes files that do not parse and reports them as
     * created.
     */
    public function test_it_refuses_a_path_segment_that_cannot_be_a_php_name(): void
    {
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);

        $exitCode = $bootstrap->run(new StringInput('make:file Psr4CodeGenerator/user-profile facade'), $output);

        $display = $output->fetch();

        self::assertSame(Command::FAILURE, $exitCode, $display);
        self::assertStringContainsString('user-profile', $display);
        self::assertStringContainsString('is not a valid PHP name', $display);

        // Refused before writing: a half-generated module is worse than none.
        self::assertFileDoesNotExist('./src/user-profile/user-profileFacade.php');
        self::assertDirectoryDoesNotExist('./src/user-profile');
    }

    public static function createFilesProvider(): iterable
    {
        yield 'facade' => ['facade', 'TestModuleFacade', false];
        yield 'factory' => ['factory', 'TestModuleFactory', false];
        yield 'config' => ['config', 'TestModuleConfig', false];
        yield 'dependency provider' => ['dependency-provider', 'TestModuleProvider', false];

        yield 'facade -s' => ['facade', 'Facade', true];
        yield 'factory -s' => ['factory', 'Factory', true];
        yield 'config -s' => ['config', 'Config', true];
        yield 'dependency provider -s' => ['dependency-provider', 'Provider', true];
    }
}
