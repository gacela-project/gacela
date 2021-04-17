<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use Gacela\Framework\Config;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeFactoryTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function test_make_factory(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/Factory.php');

        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:factory', ['GacelaTest/Integration/CodeGenerator/Generated']);

        $this->expectOutputRegex("~/Generated/Factory.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/Factory.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated/');
    }
}
