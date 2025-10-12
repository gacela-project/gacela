<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\CommandArguments;

use Gacela\Console\Domain\CommandArguments\CommandArgumentsException;
use Gacela\Console\Domain\CommandArguments\CommandArgumentsParser;
use PHPUnit\Framework\TestCase;

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

    public function test_no_autoload_psr4_match_found(): void
    {
        $this->expectExceptionMessage(
            'No autoload psr-4 match found for Unknown/Module. Known PSR-4: App, VendorPackage',
        );

        $parser = new CommandArgumentsParser($this->exampleComposerJsonWithVendorNamespace());
        $parser->parse('Unknown/Module');
    }

    public function test_parse_with_multibyte_namespace(): void
    {
        $parser = new CommandArgumentsParser($this->exampleMultibyteComposerJson());
        $args = $parser->parse('Tëst/Mödülé');

        self::assertSame('Tëst\Mödülé', $args->namespace());
        self::assertSame('src/Mödülé', $args->directory());
    }

    public function test_parse_prefers_longest_psr4_match(): void
    {
        $parser = new CommandArgumentsParser($this->exampleComposerJsonWithMultipleNamespaces());
        $args = $parser->parse('App/Test/SubModule');

        self::assertSame('App\Test\SubModule', $args->namespace());
        self::assertSame('modules/Test/SubModule', $args->directory());
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
        return json_decode($composerJson, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{autoload: array{psr-4: array<string,string>}}
     */
    private function exampleComposerJsonWithMultipleNamespaces(): array
    {
        $composerJson = <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "App\\Test\\": "modules/Test/"
        }
    }
}
JSON;
        return json_decode($composerJson, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{autoload: array{psr-4: array<string,string>}}
     */
    private function exampleComposerJsonWithVendorNamespace(): array
    {
        $composerJson = <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "Vendor\\Package\\": "packages/"
        }
    }
}
JSON;
        return json_decode($composerJson, true, 512, JSON_THROW_ON_ERROR);
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
        return json_decode($composerJson, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{autoload: array{psr-4: array<string,string>}}
     */
    private function exampleMultibyteComposerJson(): array
    {
        $composerJson = <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "Tëst\\": "src/"
        }
    }
}
JSON;
        return json_decode($composerJson, true);
    }
}
