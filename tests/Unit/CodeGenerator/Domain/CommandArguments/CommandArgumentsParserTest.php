<?php

declare(strict_types=1);

namespace GacelaTest\Unit\CodeGenerator\Domain\CommandArguments;

use Gacela\CodeGenerator\Domain\CommandArguments\CommandArgumentsException;
use Gacela\CodeGenerator\Domain\CommandArguments\CommandArgumentsParser;
use PHPUnit\Framework\TestCase;
use function json_decode;

final class CommandArgumentsParserTest extends TestCase
{
    public function test_exception_when_no_autoload_found(): void
    {
        $this->expectExceptionObject(CommandArgumentsException::noAutoloadFound());
        $parser = new CommandArgumentsParser([]);
        $parser->parse('');
    }

    public function test_exception_when_no_psr4_found(): void
    {
        $this->expectExceptionObject(CommandArgumentsException::noAutoloadPsr4Found());
        $parser = new CommandArgumentsParser(['autoload' => []]);
        $parser->parse('');
    }

    public function test_parse_one_level_from_root_namespace(): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse('App/TestModule');

        self::assertSame('App\TestModule', $args->namespace());
    }

    public function test_parse_multi_level_from_root_namespace(): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse('App/TestModule/TestSubModule');

        self::assertSame('App\TestModule\TestSubModule', $args->namespace());
    }

    public function test_parse_one_level_from_target_directory(): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse('App/TestModule');

        self::assertSame('src/TestModule', $args->directory());
    }

    public function test_parse_multi_level_from_target_directory(): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse('App/TestModule/TestSubModule');

        self::assertSame('src/TestModule/TestSubModule', $args->directory());
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
        $args = $parser->parse('App/TestModule/TestSubModule');

        self::assertSame('App\TestModule\TestSubModule', $args->namespace());
    }

    public function test_parse_multilevel_target_directory(): void
    {
        $parser = new CommandArgumentsParser($this->exampleMultiLevelComposerJson());
        $args = $parser->parse('App/TestModule/TestSubModule');

        self::assertSame('src/TestSubModule', $args->directory());
    }

    /**
     * @return array{autoload: array{psr-4: array<string,string>}}
     */
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

    public function test_no_autoload_psr4_match_found(): void
    {
        $this->expectExceptionObject(
            CommandArgumentsException::noAutoloadPsr4MatchFound('Unknown/Module')
        );

        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $parser->parse('Unknown/Module');
    }
}
