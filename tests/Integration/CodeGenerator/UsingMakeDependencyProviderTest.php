<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use GacelaTest\Integration\CodeGenerator\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class UsingMakeDependencyProviderTest extends TestCase
{
    public function test_make_dependency_provider(): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/Generated/DependencyProvider.php');

        $codeGeneratorDependencyProvider = new CodeGeneratorFacade();
        $codeGeneratorDependencyProvider->runCommand('make:dependency-provider', [__NAMESPACE__, __DIR__ . '/Generated']);

        $this->expectOutputRegex("~/Generated/DependencyProvider.php' created successfully~");
        self::assertFileExists(__DIR__ . '/Generated/DependencyProvider.php');

        DirectoryUtil::removeDir(__DIR__ . '/Generated');
    }
}
