<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\SuffixMismatchCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use PHPUnit\Framework\TestCase;

final class SuffixMismatchCheckTest extends TestCase
{
    public function test_no_modules_returns_ok(): void
    {
        $check = new SuffixMismatchCheck([], $this->defaultSuffixes());

        $result = $check->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_all_suffixes_match_defaults_returns_ok(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        $check = new SuffixMismatchCheck([$module], $this->defaultSuffixes());

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_facade_with_wrong_suffix_is_error(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFaced', // typo suffix
        );

        $check = new SuffixMismatchCheck([$module], $this->defaultSuffixes());

        $result = $check->run();
        self::assertSame(CheckStatus::Error, $result->status);
        self::assertNotEmpty($result->details);
    }

    public function test_optional_factory_with_wrong_suffix_is_warning(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactorio', // bad
        );

        $result = (new SuffixMismatchCheck([$module], $this->defaultSuffixes()))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
    }

    public function test_optional_config_with_wrong_suffix_is_warning(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            null,
            'App\\Foo\\FooSettings', // bad
        );

        $result = (new SuffixMismatchCheck([$module], $this->defaultSuffixes()))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['Config "App\\Foo\\FooSettings" does not end with any configured Config suffix [Config]'],
            $result->details,
        );
    }

    public function test_optional_provider_with_wrong_suffix_is_warning(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            null,
            null,
            'App\\Foo\\FooRegistrar', // bad
        );

        $result = (new SuffixMismatchCheck([$module], $this->defaultSuffixes()))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['Provider "App\\Foo\\FooRegistrar" does not end with any configured Provider suffix [Provider]'],
            $result->details,
        );
    }

    public function test_errors_and_warnings_are_merged_into_one_flat_detail_list(): void
    {
        $first = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFaced');
        $second = new AppModule('App\\Bar', 'Bar', 'App\\Bar\\BarFaced', 'App\\Bar\\BarFactorio');

        $result = (new SuffixMismatchCheck([$first, $second], $this->defaultSuffixes()))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame([
            'Facade "App\\Foo\\FooFaced" does not end with any configured Facade suffix [Facade]',
            'Facade "App\\Bar\\BarFaced" does not end with any configured Facade suffix [Facade]',
            'Factory "App\\Bar\\BarFactorio" does not end with any configured Factory suffix [Factory]',
        ], $result->details);
    }

    /**
     * An unconfigured suffix map falls back to the framework defaults, so a
     * module using them all must still pass.
     */
    public function test_the_framework_defaults_apply_when_no_suffix_is_configured(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        self::assertSame(CheckStatus::Ok, (new SuffixMismatchCheck([$module], []))->run()->status);
    }

    public function test_custom_suffix_is_respected(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooPublicApi',
        );

        $suffixes = $this->defaultSuffixes();
        $suffixes['Facade'][] = 'PublicApi';

        self::assertSame(CheckStatus::Ok, (new SuffixMismatchCheck([$module], $suffixes))->run()->status);
    }

    public function test_custom_factory_suffix_is_respected(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade', 'App\\Foo\\FooCreator');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Factory'][] = 'Creator';

        self::assertSame(CheckStatus::Ok, (new SuffixMismatchCheck([$module], $suffixes))->run()->status);
    }

    public function test_custom_config_suffix_is_respected(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade', null, 'App\\Foo\\FooSettings');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Config'][] = 'Settings';

        self::assertSame(CheckStatus::Ok, (new SuffixMismatchCheck([$module], $suffixes))->run()->status);
    }

    public function test_custom_provider_suffix_is_respected(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade', null, null, 'App\\Foo\\FooRegistrar');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Provider'][] = 'Registrar';

        self::assertSame(CheckStatus::Ok, (new SuffixMismatchCheck([$module], $suffixes))->run()->status);
    }

    /**
     * @return array{Facade: list<string>, Factory: list<string>, Config: list<string>, Provider: list<string>}
     */
    private function defaultSuffixes(): array
    {
        return [
            'Facade' => ['Facade'],
            'Factory' => ['Factory'],
            'Config' => ['Config'],
            'Provider' => ['Provider'],
        ];
    }
}
