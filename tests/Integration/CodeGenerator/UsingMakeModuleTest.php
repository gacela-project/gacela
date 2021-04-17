<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use Gacela\Framework\Config;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeModuleTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function test_make_module(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/Module.php');

        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:module', ['GacelaTest/Integration/CodeGenerator/Generated']);

        $this->expectOutputRegex("~/Generated/Facade.php' created successfully~");
        $this->expectOutputRegex("~/Generated/Factory.php' created successfully~");
        $this->expectOutputRegex("~/Generated/Config.php' created successfully~");
        $this->expectOutputRegex("~/Generated/DependencyProvider.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/Facade.php');
        self::assertFileExists(__DIR__ . '/Generated/Factory.php');
        self::assertFileExists(__DIR__ . '/Generated/Config.php');
        self::assertFileExists(__DIR__ . '/Generated/DependencyProvider.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
