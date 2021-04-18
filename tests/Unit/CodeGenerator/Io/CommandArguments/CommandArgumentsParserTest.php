<?php

declare(strict_types=1);

namespace GacelaTest\Unit\CodeGenerator\Io\CommandArguments;

use Gacela\CodeGenerator\Domain\Io\CommandArguments\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\Io\CommandArguments\Exception\CommandArgumentsException;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function json_decode;

final class CommandArgumentsParserTest extends TestCase
{
    public function test_exception_when_no_arguments_are_given(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $parser = new CommandArgumentsParser([]);
        $parser->parse([]);
    }

    public function test_exception_when_no_autoload_found(): void
    {
        $this->expectExceptionObject(CommandArgumentsException::noAutoloadFound());
        $parser = new CommandArgumentsParser([]);
        $parser->parse(['']);
    }

    public function test_exception_when_no_psr4_found(): void
    {
        $this->expectExceptionObject(CommandArgumentsException::noAutoloadPsr4Found());
        $parser = new CommandArgumentsParser(['autoload' => []]);
        $parser->parse(['']);
    }

    /**
     * @dataProvider providerOneLevelRootNamespace
     */
    public function test_parse_one_level_root_namespace(array $arguments, string $expected): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse($arguments);

        self::assertSame($expected, $args->namespace());
    }

    public function providerOneLevelRootNamespace(): Generator
    {
        yield 'One level composer psr-4 and one level namespace' => [
            'arguments' => ['App/TestModule'],
            'expected' => 'App\TestModule',
        ];

        yield 'One level composer psr-4 but multiple level namespace' => [
            'arguments' => ['App/TestModule/TestSubModule'],
            'expected' => 'App\TestModule\TestSubModule',
        ];
    }

    /**
     * @dataProvider providerOneLevelTargetDirectory
     */
    public function test_parse_one_level_target_directory(array $arguments, string $expected): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse($arguments);

        self::assertSame($expected, $args->directory());
    }

    public function providerOneLevelTargetDirectory(): Generator
    {
        yield 'One level composer psr-4 and one level namespace' => [
            'arguments' => ['App/TestModule'],
            'expected' => 'src/TestModule',
        ];

        yield 'One level composer psr-4 but multiple level namespace' => [
            'arguments' => ['App/TestModule/TestSubModule'],
            'expected' => 'src/TestModule/TestSubModule',
        ];
    }

    private function exampleOneLevelComposerJson(): array
    {
        $composerJson = <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
JSON;
        return json_decode($composerJson, true);
    }

    public function test_parse_multilevel_root_namespace(): void
    {
        $parser = new CommandArgumentsParser($this->exampleMultiLevelComposerJson());
        $args = $parser->parse(['App/TestModule/TestSubModule']);

        self::assertSame('App\TestModule\TestSubModule', $args->namespace());
    }

    public function test_parse_multilevel_target_directory(): void
    {
        $parser = new CommandArgumentsParser($this->exampleMultiLevelComposerJson());
        $args = $parser->parse(['App/TestModule/TestSubModule']);

        self::assertSame('src/TestSubModule', $args->directory());
    }

    private function exampleMultiLevelComposerJson(): array
    {
        $composerJson = <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "App\\TestModule\\": "src/"
        }
    }
}
JSON;
        return json_decode($composerJson, true);
    }
}
