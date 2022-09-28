<?php

declare(strict_types=1);

namespace GacelaTest\Feature\CodeGenerator;

use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MakeModuleCommandTest extends TestCase
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
     * @dataProvider createModulesProvider
     */
    public function test_make_module(string $fileName, string $shortName): void
    {
        $input = new StringInput("Gacela/TestModule {$shortName}");
        $output = new BufferedOutput();

        $command = new MakeModuleCommand();
        $command->run($input, $output);

        $expectedOutput = <<<OUT
> Path 'src/TestModule/{$fileName}Facade.php' created successfully
> Path 'src/TestModule/{$fileName}Factory.php' created successfully
> Path 'src/TestModule/{$fileName}Config.php' created successfully
> Path 'src/TestModule/{$fileName}DependencyProvider.php' created successfully
Module 'TestModule' created successfully
OUT;

        self::assertSame($expectedOutput, trim($output->fetch()));

        self::assertFileExists(self::ENTRY_POINT . "src/TestModule/{$fileName}Facade.php");
        self::assertFileExists(self::ENTRY_POINT . "src/TestModule/{$fileName}Factory.php");
        self::assertFileExists(self::ENTRY_POINT . "src/TestModule/{$fileName}Config.php");
        self::assertFileExists(self::ENTRY_POINT . "src/TestModule/{$fileName}DependencyProvider.php");
    }

    public function createModulesProvider(): iterable
    {
        yield 'module' => ['TestModule', ''];
        yield 'module -s' => ['', '-s'];
    }
}
