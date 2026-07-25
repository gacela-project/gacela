<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\FilenameMismatchCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use PHPUnit\Framework\TestCase;

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

        $check = new FilenameMismatchCheck([$module], static function (string $class): string {
            return $class === 'App\\Foo\\Provider'
                ? '/app/Foo/DependencyProvider.php'
                : '/app/Foo/' . self::shortName($class) . '.php';
        });

        $result = $check->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertStringContainsString('App\\Foo\\Provider', $result->details[0]);
        self::assertStringContainsString('DependencyProvider.php', $result->details[0]);
        self::assertStringContainsString('Provider.php', $result->remediation);
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
