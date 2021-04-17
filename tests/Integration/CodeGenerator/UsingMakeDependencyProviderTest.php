<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use Gacela\Framework\Config;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeDependencyProviderTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function test_make_dependency_provider(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/DependencyProvider.php');

        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:dependency-provider', ['GacelaTest/Integration/CodeGenerator/Generated']);

        $this->expectOutputRegex("~/Generated/DependencyProvider.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/DependencyProvider.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
