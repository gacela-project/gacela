<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FileContent;

use Gacela\Console\Domain\FileContent\PhpValue;
use PHPUnit\Framework\TestCase;
use stdClass;

use function fclose;
use function fopen;

final class PhpValueTest extends TestCase
{
    /**
     * The reason this exists. `var_export()` writes `array (` on its own line
     * and indents from column zero, which mangles anything already formatted:
     * a generated constructor call, a one-parameter-per-line listing, and a
     * JSON string field.
     */
    public function test_no_export_contains_a_line_break(): void
    {
        foreach ([[], [1, 2], ['a' => 1], ['a' => ['b' => ['c' => 1]]], 'x', 1, null] as $value) {
            self::assertStringNotContainsString(
                "\n",
                PhpValue::export($value),
                'a line break would break every caller',
            );
        }
    }

    public function test_an_empty_array_is_the_short_syntax(): void
    {
        self::assertSame('[]', PhpValue::export([]));
    }

    /**
     * A list writes its values alone: `[0 => 'eur', 1 => 'usd']` is the same
     * array spelled longer, and these are read by people.
     */
    public function test_a_list_omits_its_keys(): void
    {
        self::assertSame("['eur', 'usd']", PhpValue::export(['eur', 'usd']));
    }

    public function test_a_map_keeps_its_keys(): void
    {
        self::assertSame("['a' => 1, 'b' => 2]", PhpValue::export(['a' => 1, 'b' => 2]));
    }

    public function test_a_nested_array_stays_on_one_line(): void
    {
        self::assertSame("['a' => 1, 'b' => ['c' => 2]]", PhpValue::export(['a' => 1, 'b' => ['c' => 2]]));
    }

    /**
     * An array that is a list of maps is the shape a declared default most
     * often has, and it exercises both branches at once.
     */
    public function test_a_list_of_maps_renders_both_ways_at_once(): void
    {
        self::assertSame(
            "[['id' => 1], ['id' => 2]]",
            PhpValue::export([['id' => 1], ['id' => 2]]),
        );
    }

    /**
     * A key that is a string still gets quoted, and one that is an int does
     * not -- which is what makes a non-list map read back as itself.
     */
    public function test_integer_keys_on_a_non_list_are_kept_unquoted(): void
    {
        self::assertSame('[5 => 1, 9 => 2]', PhpValue::export([5 => 1, 9 => 2]));
    }

    /**
     * Scalars go through `var_export()` on purpose: it already quotes and
     * escapes strings the way PHP source needs.
     */
    public function test_a_string_is_quoted_and_escaped(): void
    {
        self::assertSame("'it\\'s'", PhpValue::export("it's"));
        self::assertSame("'EUR'", PhpValue::export('EUR'));
    }

    public function test_scalars_keep_their_php_spelling(): void
    {
        self::assertSame('null', PhpValue::export(null));
        self::assertSame('true', PhpValue::export(true));
        self::assertSame('false', PhpValue::export(false));
        self::assertSame('3', PhpValue::export(3));
        self::assertSame('1.5', PhpValue::export(1.5));
    }

    /**
     * An object has no literal to write. It is described rather than rendered,
     * so it cannot read as source somebody pastes back -- `var_export()` would
     * emit a `\stdClass::__set_state(...)` call that does not even run.
     */
    public function test_an_object_is_described_rather_than_rendered(): void
    {
        self::assertSame('object(stdClass)', PhpValue::export(new stdClass()));
    }

    public function test_a_resource_is_described(): void
    {
        $handle = fopen('php://memory', 'rb');

        self::assertSame('resource', PhpValue::export($handle));

        if ($handle !== false) {
            fclose($handle);
        }
    }
}
