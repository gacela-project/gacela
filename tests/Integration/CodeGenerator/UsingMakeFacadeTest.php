<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeFacadeTest extends TestCase
{
    public function test_make_facade(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/Facade.php');

        $codeGeneratorFacade = new CodeGeneratorFacade();
        $codeGeneratorFacade->runCommand('make:facade', [__NAMESPACE__, __DIR__ . '/Generated']);

        $this->expectOutputRegex("~/Generated/Facade.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/Facade.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
