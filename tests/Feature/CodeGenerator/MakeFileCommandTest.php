<?php

declare(strict_types=1);

namespace GacelaTest\Feature\CodeGenerator;

use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class MakeFileCommandTest extends TestCase
{
    private const ENTRY_POINT = __DIR__ . '/../../../';

    public function setUp(): void
    {
        DirectoryUtil::removeDir(self::ENTRY_POINT . 'src/TestModule');
    }

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::ENTRY_POINT . 'src/TestModule');
    }

    /**
     * @dataProvider createFilesProvider
     */
    public function test_make_file(string $action, string $fileName, string $shortName): void
    {
        $command = sprintf('%sgacela make:file %s Gacela/TestModule %s', self::ENTRY_POINT, $shortName, $action);
        exec($command, $output);

        self::assertSame("> Path 'src/TestModule/{$fileName}.php' created successfully", $output[0]);
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
