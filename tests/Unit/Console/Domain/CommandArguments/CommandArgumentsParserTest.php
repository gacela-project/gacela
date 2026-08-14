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

    /**
     * `autoload-dev` prefixes are resolvable -- that is what "Read autoload-dev
     * psr-4 namespaces for gacela make commands" shipped. Pinned here because
     * nothing else at this level says so, which is how the message below came
     * to disagree with it.
     */
    public function test_a_namespace_declared_only_in_autoload_dev_resolves(): void
    {
        $parser = new CommandArgumentsParser($this->exampleComposerJsonWithAutoloadDev());

        self::assertSame('tests/Wallet', $parser->parse('AppTest/Wallet')->directory());
    }

    /**
     * The list is the only thing the reader has to compare a typo against, so
     * it has to be every prefix the lookup searched. It was built from
     * `autoload` alone while the lookup used `autoload + autoload-dev`: someone
     * mistyping a dev namespace was shown a list without a single dev prefix in
     * it, and would conclude Gacela does not read them -- about namespaces it
     * had just been willing to resolve.
     */
    public function test_the_known_list_names_the_autoload_dev_prefixes_too(): void
    {
        $this->expectExceptionMessage(
            'No autoload psr-4 match found for AppTst/Wallet. Known PSR-4: App, AppTest, Fixtures',
        );

        $parser = new CommandArgumentsParser($this->exampleComposerJsonWithAutoloadDev());
        $parser->parse('AppTst/Wallet');
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

    /**
     * The psr-4 key and value are trimmed of their trailing separator by
     * character, not by byte: with a multibyte path, cutting one byte leaves a
     * broken sequence and the resulting directory no longer matches anything.
     */
    public function test_parse_with_a_multibyte_psr4_path(): void
    {
        $composerJson = json_decode(
            '{"autoload":{"psr-4":{"Aplicación\\\\":"código/"}}}',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $args = (new CommandArgumentsParser($composerJson))->parse('Aplicación/TestModule');

        self::assertSame('Aplicación\TestModule', $args->namespace());
        self::assertSame('código/TestModule', $args->directory());
    }

    /**
     * The module name repeats the namespace text: "Application" starts with
     * "App". Replacing the namespace globally rewrote that occurrence too, so
     * the directory came back as `src/srclication`.
     */
    public function test_parse_module_name_that_repeats_the_namespace(): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse('App/Application');

        self::assertSame('App\Application', $args->namespace());
        self::assertSame('src/Application', $args->directory());
    }

    /**
     * Composer does not require the trailing slash on a psr-4 target. Cutting
     * the last character unconditionally turned `src` into `sr`.
     */
    public function test_parse_psr4_target_without_a_trailing_slash(): void
    {
        $composerJson = json_decode(
            '{"autoload":{"psr-4":{"App\\\\":"src"}}}',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $args = (new CommandArgumentsParser($composerJson))->parse('App/TestModule');

        self::assertSame('App\TestModule', $args->namespace());
        self::assertSame('src/TestModule', $args->directory());
    }

    /**
     * Composer allows a list of directories per namespace. Only the first is
     * used: it is where Composer itself would look first, and generating into
     * any of the others would be a guess.
     */
    public function test_parse_psr4_target_given_as_a_list_uses_the_first_directory(): void
    {
        $composerJson = json_decode(
            '{"autoload":{"psr-4":{"App\\\\":["src/","generated/"]}}}',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $args = (new CommandArgumentsParser($composerJson))->parse('App/TestModule');

        self::assertSame('App\TestModule', $args->namespace());
        self::assertSame('src/TestModule', $args->directory());
    }

    /**
     * The namespace root on its own, with nothing after it.
     */
    public function test_parse_the_root_namespace_itself(): void
    {
        $parser = new CommandArgumentsParser($this->exampleOneLevelComposerJson());
        $args = $parser->parse('App');

        self::assertSame('App', $args->namespace());
        self::assertSame('src', $args->directory());
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
     * @return array{autoload: array{psr-4: array<string,string>}, autoload-dev: array{psr-4: array<string,string>}}
     */
    private function exampleComposerJsonWithAutoloadDev(): array
    {
        $composerJson = <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "AppTest\\": "tests/",
            "Fixtures\\": "fixtures/"
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
