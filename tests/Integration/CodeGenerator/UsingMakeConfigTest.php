<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeConfigTest extends TestCase
{
    public function test_make_config(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/Config.php');

        $codeGeneratorConfig = new CodeGeneratorFacade();
        $codeGeneratorConfig->runCommand('make:config', [__NAMESPACE__, __DIR__ . '/Generated']);

        $this->expectOutputRegex("~/Generated/Config.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/Config.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
