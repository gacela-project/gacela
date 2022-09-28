<?php

declare(strict_types=1);

namespace GacelaTest\Feature\CodeGenerator;

use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MakeFileCommandTest extends TestCase
{
    private const ENTRY_POINT = __DIR__ . '/../../../';

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::ENTRY_POINT . 'src/TestModule');
    }

    public function setUp(): void
    {
        Gacela::bootstrap(self::ENTRY_POINT);
        DirectoryUtil::removeDir(self::ENTRY_POINT . 'src/TestModule');
    }

    /**
     * @dataProvider createFilesProvider
     */
    public function test_make_file(string $action, string $fileName, string $shortName): void
    {
        $input = new StringInput(sprintf('%s Gacela/TestModule %s', $shortName, $action));
        $output = new BufferedOutput();

        $command = new MakeFileCommand();
        $command->run($input, $output);

        self::assertSame("> Path 'src/TestModule/{$fileName}.php' created successfully", trim($output->fetch()));
        self::assertFileExists(self::ENTRY_POINT . "src/TestModule/{$fileName}.php");
    }

    public function createFilesProvider(): iterable
    {
        yield 'facade' => ['facade', 'TestModuleFacade', ''];
        yield 'factory' => ['factory', 'TestModuleFactory', ''];
        yield 'config' => ['config', 'TestModuleConfig', ''];
        yield 'dependency provider' => ['dependency-provider', 'TestModuleDependencyProvider', ''];

        // Sort name flag
        yield 'facade -s' => ['facade', 'Facade', '-s'];
        yield 'factory -s' => ['factory', 'Factory', '-s'];
        yield 'config -s' => ['config', 'Config', '-s'];
        yield 'dependency provider -s' => ['dependency-provider', 'DependencyProvider', '-s'];
    }
}
