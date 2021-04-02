<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UsingIncorrectConfigurationTest extends TestCase
{
    public function test_make_unknown_command(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $codeGeneratorConfig = new CodeGeneratorFacade();
        $codeGeneratorConfig->runCommand('make:unknown', [__NAMESPACE__, __DIR__ . '/Generated']);
    }

    public function test_missing_target_directory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $codeGeneratorConfig = new CodeGeneratorFacade();
        $codeGeneratorConfig->runCommand('', [__NAMESPACE__]);
    }

    public function test_missing_root_namespace_and_target_directory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $codeGeneratorConfig = new CodeGeneratorFacade();
        $codeGeneratorConfig->runCommand('', []);
    }
}
