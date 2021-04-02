<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeFactoryTest extends TestCase
{
    public function test_make_factory(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/Factory.php');

        $codeGeneratorFactory = new CodeGeneratorFacade();
        $codeGeneratorFactory->runCommand('make:factory', [__NAMESPACE__, __DIR__ . '/Generated']);

        $this->expectOutputRegex("~/Generated/Factory.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/Factory.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
