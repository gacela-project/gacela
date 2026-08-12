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
     * A kind the project declared is carried like any other, so the map the
     * check reads is no longer the fixed four.
     */
    public function test_a_declared_kind_without_a_discovered_class_reports_nothing(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        $suffixes = $this->defaultSuffixes();
        $suffixes['Exporter'] = ['Exporter', 'Feed'];

        $result = (new SuffixMismatchCheck([$module], $suffixes))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 module(s) use configured suffixes'], $result->details);
    }

    /**
     * A map that names only a declared kind still knows the pillars: the four
     * are the floor the configured map is read on top of, never a replacement.
     */
    public function test_a_map_naming_only_a_declared_kind_still_knows_the_pillars(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        $result = (new SuffixMismatchCheck([$module], ['Exporter' => ['Exporter']]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    /**
     * The difference between "rename this" and a day spent asking why the
     * facade is not found: the name is not unsuffixed, it is *taken*, and the
     * kind that took it is one the project itself declared.
     */
    public function test_a_class_ending_with_a_declared_kinds_suffix_names_that_kind(): void
    {
        $module = new AppModule('App\\Report', 'Report', 'App\\Report\\ReportExporter');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Exporter'] = ['Exporter', 'Feed'];

        $result = (new SuffixMismatchCheck([$module], $suffixes))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame([
            'Facade "App\\Report\\ReportExporter" does not end with any configured Facade suffix [Facade]'
            . '; "Exporter" is configured for the "Exporter" type',
        ], $result->details);
    }

    /**
     * The optional pillars read the declared kinds too, not just the facade.
     */
    public function test_an_optional_pillar_ending_with_a_declared_kinds_suffix_names_that_kind(): void
    {
        $module = new AppModule('App\\Report', 'Report', 'App\\Report\\ReportFacade', 'App\\Report\\ReportFeed');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Exporter'] = ['Exporter', 'Feed'];

        $result = (new SuffixMismatchCheck([$module], $suffixes))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([
            'Factory "App\\Report\\ReportFeed" does not end with any configured Factory suffix [Factory]'
            . '; "Feed" is configured for the "Exporter" type',
        ], $result->details);
    }

    /**
     * Longest suffix first, the same order the resolver matches in: a declared
     * `ServiceProvider` owns the name a built-in `Provider` merely ends in.
     */
    public function test_the_longest_suffix_names_the_kind_when_two_could_match(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooServiceProvider');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Service'] = ['ServiceProvider'];

        $result = (new SuffixMismatchCheck([$module], $suffixes))->run();

        self::assertSame([
            'Facade "App\\Foo\\FooServiceProvider" does not end with any configured Facade suffix [Facade]'
            . '; "ServiceProvider" is configured for the "Service" type',
        ], $result->details);
    }

    /**
     * A suffix two kinds share cannot say which kind a name is -- the resolver
     * drops it for exactly that reason -- so the report must not claim one.
     */
    public function test_a_suffix_two_kinds_share_names_neither(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooExtra');

        $suffixes = $this->defaultSuffixes();
        $suffixes['Factory'][] = 'Extra';
        $suffixes['Config'][] = 'Extra';

        $result = (new SuffixMismatchCheck([$module], $suffixes))->run();

        self::assertSame(
            ['Facade "App\\Foo\\FooExtra" does not end with any configured Facade suffix [Facade]'],
            $result->details,
        );
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
