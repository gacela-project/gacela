<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use function array_is_list;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function var_export;

/**
 * A PHP value as the source you would write for it, on one line.
 *
 * `var_export()` is the obvious way to do this and is wrong wherever the result
 * is placed inside something already formatted. It emits `array (` on its own
 * line and indents the body from column zero, so an array default rendered into
 * a constructor call came out as:
 *
 *     $data['meta'] ?? array (
 *   'a' => 1,
 *   'b' =>
 *   array (
 *     'c' => 2,
 *   ),
 * ),
 *
 * -- valid PHP, mangled to read, and committed by whoever ran `dto:generate`.
 * The same call put line breaks into a `debug:dependencies` listing, whose
 * whole shape is one parameter per line, and into the `detail` field of the
 * JSON those commands emit.
 *
 * Scalars are still `var_export()`: it already quotes and escapes strings the
 * way PHP source needs, and there is nothing to reformat.
 */
final class PhpValue
{
    public static function export(mixed $value): string
    {
        if (is_array($value)) {
            return self::exportArray($value);
        }

        // `var_export()` spells it `NULL`, and the same generator writes a
        // lowercase `null` for a property with no declared default -- so both
        // spellings would sit in one generated file.
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return var_export($value, true);
        }

        // An object or a resource has no literal to write. Described rather
        // than rendered, so it cannot read as source somebody pastes back.
        return is_object($value) ? 'object(' . $value::class . ')' : 'resource';
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function exportArray(array $value): string
    {
        // No case for the empty array: the loop below adds nothing and the
        // brackets are written either way, so `[]` falls out of the general
        // path. A special case for it was dead the day it was written.
        $isList = array_is_list($value);
        $parts = [];

        /** @var mixed $item */
        foreach ($value as $key => $item) {
            // A list writes its values alone: `[0 => 'eur', 1 => 'usd']` is the
            // same array spelled longer, and these are read by people.
            $parts[] = $isList
                ? self::export($item)
                : var_export($key, true) . ' => ' . self::export($item);
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
