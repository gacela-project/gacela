<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\ConfigDimensions;
use Gacela\Framework\Exception\ConfigDimensionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigDimensionsTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_REGION');
        putenv('APP_TENANT');
    }

    public function test_declaring_nothing_resolves_to_nothing(): void
    {
        $dimensions = ConfigDimensions::fromEnvironment([]);

        self::assertTrue($dimensions->isEmpty());
        self::assertSame([], $dimensions->values());
    }

    public function test_declared_variables_resolve_in_declaration_order(): void
    {
        putenv('APP_REGION=eu');
        putenv('APP_TENANT=acme');

        $dimensions = ConfigDimensions::fromEnvironment(['APP_REGION', 'APP_TENANT']);

        self::assertSame(['eu', 'acme'], $dimensions->values());
    }

    /**
     * The chain ends at the first unset variable rather than skipping it: a
     * tenant without a region would produce `app-prod--acme.php`, a file with
     * a hole in it and no meaning, and it would break the prefix property that
     * keeps `config/*-prod.php` from matching `app-prod-eu.php`.
     */
    public function test_an_unset_variable_ends_the_chain_rather_than_being_skipped(): void
    {
        putenv('APP_TENANT=acme');

        $dimensions = ConfigDimensions::fromEnvironment(['APP_REGION', 'APP_TENANT']);

        self::assertSame([], $dimensions->values());
    }

    public function test_an_empty_value_counts_as_unset(): void
    {
        putenv('APP_REGION=');

        self::assertSame([], ConfigDimensions::fromEnvironment(['APP_REGION'])->values());
    }

    #[DataProvider('refusedValueProvider')]
    public function test_a_value_a_glob_or_a_filename_could_not_carry_is_refused(string $value): void
    {
        putenv('APP_REGION=' . $value);

        $this->expectException(ConfigDimensionException::class);
        $this->expectExceptionMessage('APP_REGION');

        ConfigDimensions::fromEnvironment(['APP_REGION']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedValueProvider(): iterable
    {
        yield 'directory traversal' => ['../etc'];
        yield 'path separator' => ['eu/west'];
        yield 'glob wildcard' => ['e*'];
        yield 'space' => ['eu west'];
    }

    public function test_the_ordinary_alphabet_is_accepted(): void
    {
        putenv('APP_REGION=eu-west_1.a');

        self::assertSame(['eu-west_1.a'], ConfigDimensions::fromEnvironment(['APP_REGION'])->values());
    }

    public function test_the_chain_starts_at_the_environment_and_grows_one_link_per_value(): void
    {
        $dimensions = ConfigDimensions::fromValues(['eu', 'acme']);

        self::assertSame(['prod', 'prod-eu', 'prod-eu-acme'], $dimensions->suffixChain('prod'));
    }

    public function test_without_dimensions_the_chain_is_the_environment_alone(): void
    {
        self::assertSame(['prod'], ConfigDimensions::none()->suffixChain('prod'));
    }

    /**
     * No environment means no suffixed file to look for, dimensions or not:
     * `app--eu.php` names nothing.
     */
    public function test_without_an_environment_there_is_no_chain_at_all(): void
    {
        self::assertSame([], ConfigDimensions::fromValues(['eu'])->suffixChain(''));
    }
}
