<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Symfony\Component\Console\Input\InputInterface;

use function is_string;

/**
 * Reads console arguments and options as strings.
 *
 * Symfony types getArgument()/getOption() as `mixed` because an input
 * definition may declare array or boolean values. The Gacela commands only
 * declare scalar, optional arguments and options, so a non-string value means
 * "absent" and collapses to the empty string, keeping the call sites free of
 * unchecked `(string)` casts on `mixed`.
 */
final class ConsoleInput
{
    public static function argument(InputInterface $input, string $name): string
    {
        return self::asString($input->getArgument($name));
    }

    public static function option(InputInterface $input, string $name): string
    {
        return self::asString($input->getOption($name));
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
