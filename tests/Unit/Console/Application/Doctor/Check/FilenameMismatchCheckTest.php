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
        $check = new FilenameMismatchCheck([], static fn (string $c): ?string => null);

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
            static fn (string $class): string => '/app/Foo/' . self::shortName($class) . '.php',
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

        $check = new FilenameMismatchCheck([$module], static fn (string $class): string => $class === 'App\\Foo\\Provider'
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
            static fn (string $class): string => 'C:\\app\\Foo\\FooFacade.php',
        );

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_unlocatable_class_is_skipped_not_reported(): void
    {
        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade');

        $check = new FilenameMismatchCheck([$module], static fn (string $c): ?string => null);

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
            static fn (string $class): string => '/app/Foo/Wrong.php',
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertCount(4, $result->details);
    }

    private static function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
