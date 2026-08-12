<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\FilenameMismatchCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\BrokenModule\BrokenModuleFacade;
use PHPUnit\Framework\TestCase;
use stdClass;

use function end;
use function explode;

final class FilenameMismatchCheckTest extends TestCase
{
    public function test_no_modules_returns_ok(): void
    {
        $check = new FilenameMismatchCheck([], fileResolver: static fn (string $c): ?string => null);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_matching_filenames_return_ok(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
        );

        $check = new FilenameMismatchCheck(
            [$module],
            fileResolver: static fn (string $class): string => '/app/Foo/' . self::shortName($class) . '.php',
        );

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    /**
     * The migration trap: the class is renamed to `Provider` but the file is
     * still `DependencyProvider.php`. Pillars resolve by filename suffix, so
     * the module silently stops resolving.
     */
    public function test_class_renamed_but_file_not_is_an_error(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            null,
            null,
            'App\\Foo\\Provider',
        );

        $check = new FilenameMismatchCheck([$module], fileResolver: static fn (string $class): string => $class === 'App\\Foo\\Provider'
            ? '/app/Foo/DependencyProvider.php'
            : '/app/Foo/' . self::shortName($class) . '.php');

        $result = $check->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['App\\Foo\\Provider is declared in DependencyProvider.php, expected Provider.php'],
            $result->details,
        );
        self::assertSame(
            'rename the file to match the class — pillars resolve by filename, '
            . 'so `Provider` declared in `DependencyProvider.php` is never found',
            $result->remediation,
        );
    }

    /**
     * The same trap, but resolved through the production file resolver instead
     * of an injected one, so the reflection path is exercised too.
     */
    public function test_the_default_file_resolver_catches_a_class_renamed_without_its_file(): void
    {
        require_once __DIR__ . '/Fixtures/DependencyProvider.php';

        $module = new AppModule(
            'GacelaTest\\Fixtures',
            'Fixtures',
            \GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\Provider::class,
        );

        $result = (new FilenameMismatchCheck([$module]))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertStringContainsString(
            'is declared in DependencyProvider.php, expected Provider.php',
            $result->details[0],
        );
    }

    /**
     * The case the check exists for, in the state it actually occurs in: the
     * mismatched file is simply sitting in the module directory. It cannot
     * autoload under PSR-4, so the pillar resolves to null and never reaches
     * the check through the module's pillar list.
     */
    public function test_a_mismatched_file_is_found_even_though_its_class_cannot_autoload(): void
    {
        $module = new AppModule(
            'GacelaTest\\Unit\\Console\\Application\\Doctor\\Check\\Fixtures\\BrokenModule',
            'BrokenModule',
            BrokenModuleFacade::class,
        );

        $result = (new FilenameMismatchCheck([$module]))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertStringContainsString(
            'is declared in DependencyProvider.php, expected Provider.php',
            $result->details[0],
        );
    }

    public function test_the_default_file_resolver_accepts_a_class_that_matches_its_file(): void
    {
        $module = new AppModule('Gacela\\Doctor', 'Doctor', FilenameMismatchCheck::class);

        self::assertSame(CheckStatus::Ok, (new FilenameMismatchCheck([$module]))->run()->status);
    }

    /**
     * Internal classes have no source file at all; they are skipped, not
     * reported as a mismatch.
     */
    public function test_the_default_file_resolver_skips_a_class_without_a_source_file(): void
    {
        $module = new AppModule('Php\\Internal', 'Internal', stdClass::class);

        self::assertSame(CheckStatus::Ok, (new FilenameMismatchCheck([$module]))->run()->status);
    }

    public function test_windows_style_paths_are_split_on_backslashes_too(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade');

        $check = new FilenameMismatchCheck(
            [$module],
            fileResolver: static fn (string $class): string => 'C:\\app\\Foo\\FooFacade.php',
        );

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_unlocatable_class_is_skipped_not_reported(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade');

        $check = new FilenameMismatchCheck([$module], fileResolver: static fn (string $c): ?string => null);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_every_pillar_is_inspected(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        $check = new FilenameMismatchCheck(
            [$module],
            fileResolver: static fn (string $class): string => '/app/Foo/Wrong.php',
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertCount(4, $result->details);
    }

    /**
     * A declared kind resolves by filename exactly like a pillar does, so a
     * class whose file disagrees with it fails the same silent way -- and the
     * directory pass already walks past the file that proves it.
     */
    public function test_a_declared_kind_whose_file_disagrees_with_its_class_is_reported(): void
    {
        $result = $this->scanReportModule(['Exporter' => ['Exporter', 'Feed']])->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['App\\Report\\ReportExporter is declared in Exporter.php, expected ReportExporter.php'],
            $result->details,
        );
    }

    /**
     * The same file, in a project that declared no such kind: nothing resolves
     * by that name, so nothing is Gacela's business to report.
     */
    public function test_a_suffix_no_kind_claims_is_not_reported(): void
    {
        self::assertSame(CheckStatus::Ok, $this->scanReportModule([])->run()->status);
    }

    /**
     * A pillar widened with a further suffix is read through the same map.
     */
    public function test_a_suffix_added_to_a_pillar_is_read_too(): void
    {
        $module = new AppModule('App\\Report', 'Report', 'App\\Report\\ReportFacade');

        $check = new FilenameMismatchCheck(
            [$module],
            ['Facade' => ['Facade', 'PublicApi']],
            fileResolver: static fn (string $class): string => '/app/Report/ReportFacade.php',
            directoryScanner: static fn (string $dir): array => ['/app/Report/Api.php'],
            declaredClassReader: static fn (string $file): string => 'ReportPublicApi',
        );

        self::assertSame(
            ['App\\Report\\ReportPublicApi is declared in Api.php, expected ReportPublicApi.php'],
            $check->run()->details,
        );
    }

    /**
     * The configured map is read on top of the four, never instead of them: a
     * project that declares a kind must not stop seeing its own providers.
     */
    public function test_a_map_naming_only_a_declared_kind_still_reads_the_pillars(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade');

        $check = new FilenameMismatchCheck(
            [$module],
            ['Exporter' => ['Exporter']],
            fileResolver: static fn (string $class): string => '/app/Foo/FooFacade.php',
            directoryScanner: static fn (string $dir): array => ['/app/Foo/DependencyProvider.php'],
            declaredClassReader: static fn (string $file): string => 'FooProvider',
        );

        self::assertSame(
            ['App\\Foo\\FooProvider is declared in DependencyProvider.php, expected FooProvider.php'],
            $check->run()->details,
        );
    }

    /**
     * A module directory holding `ReportExporter` in `Exporter.php`, seen
     * through the directory pass -- the only pass that sees a class whose file
     * keeps it from autoloading.
     *
     * @param array<string, list<string>> $suffixTypes
     */
    private function scanReportModule(array $suffixTypes): FilenameMismatchCheck
    {
        return new FilenameMismatchCheck(
            [new AppModule('App\\Report', 'Report', 'App\\Report\\ReportFacade')],
            $suffixTypes,
            fileResolver: static fn (string $class): string => '/app/Report/ReportFacade.php',
            directoryScanner: static fn (string $dir): array => [
                '/app/Report/ReportFacade.php',
                '/app/Report/Exporter.php',
            ],
            declaredClassReader: static fn (string $file): string => $file === '/app/Report/Exporter.php'
                ? 'ReportExporter'
                : 'ReportFacade',
        );
    }

    private static function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
