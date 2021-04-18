<?php

declare(strict_types=1);

namespace GacelaTest\Integration\CodeGenerator;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use Gacela\CodeGenerator\Domain\Io\CommandArguments\Exception\CommandArgumentsException;
use Gacela\Framework\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UsingIncorrectConfigurationTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function test_make_unknown_command(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:unknown', ['GacelaTest/Integration/CodeGenerator/Generated']);
    }

    public function test_missing_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:module', []);
    }

    public function test_unknown_target(): void
    {
        $this->expectExceptionObject(CommandArgumentsException::noAutoloadPsr4MatchFound('UnknownNamespace'));
        $facade = new CodeGeneratorFacade();
        $facade->runCommand('make:module', ['UnknownNamespace']);
    }
}
