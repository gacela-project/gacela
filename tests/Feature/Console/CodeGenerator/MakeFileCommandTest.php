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

final class MakeFileCommandTest extends TestCase
{
    private const CACHE_DIR = '.' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestModule';

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    #[DataProvider('createFilesProvider')]
    public function test_make_file(string $action, string $fileName, string $shortName): void
    {
        $input = new StringInput(sprintf('make:file Psr4CodeGenerator/TestModule %s %s', $action, $shortName));
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        self::assertSame(sprintf("> Path 'src/TestModule/%s.php' created successfully", $fileName), trim($output->fetch()));
        self::assertFileExists(sprintf('./src/TestModule/%s.php', $fileName));
    }

    public static function createFilesProvider(): iterable
    {
        yield 'facade' => ['facade', 'TestModuleFacade', ''];
        yield 'factory' => ['factory', 'TestModuleFactory', ''];
        yield 'config' => ['config', 'TestModuleConfig', ''];
        yield 'dependency provider' => ['dependency-provider', 'TestModuleProvider', ''];

        // Sort name flag
        yield 'facade -s' => ['facade', 'Facade', '-s'];
        yield 'factory -s' => ['factory', 'Factory', '-s'];
        yield 'config -s' => ['config', 'Config', '-s'];
        yield 'dependency provider -s' => ['dependency-provider', 'Provider', '-s'];
    }
}
