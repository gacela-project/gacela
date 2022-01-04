<?php

declare(strict_types=1);

namespace GacelaTest\Feature\CodeGenerator;

use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class MakeModuleCommandTest extends TestCase
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
     * @dataProvider createModulesProvider
     */
    public function test_make_module(string $fileName, string $shortName): void
    {
        $command = sprintf('%sgacela make:module %s Gacela/TestModule', self::ENTRY_POINT, $shortName);
        exec($command, $output);

        self::assertSame("> Path 'src/TestModule/{$fileName}Facade.php' created successfully", $output[0]);
        self::assertSame("> Path 'src/TestModule/{$fileName}Factory.php' created successfully", $output[1]);
        self::assertSame("> Path 'src/TestModule/{$fileName}Config.php' created successfully", $output[2]);
        self::assertSame("> Path 'src/TestModule/{$fileName}DependencyProvider.php' created successfully", $output[3]);
        self::assertSame("Module 'TestModule' created successfully", $output[4]);

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
