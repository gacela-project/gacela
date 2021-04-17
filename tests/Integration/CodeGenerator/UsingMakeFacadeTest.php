<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use Gacela\Framework\Config;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeFacadeTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function test_make_facade(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/Facade.php');

        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:facade', ['GacelaTest/Integration/CodeGenerator/Generated']);

        $this->expectOutputRegex("~/Generated/Facade.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/Facade.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
