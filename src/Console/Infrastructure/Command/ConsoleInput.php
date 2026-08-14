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

    /**
     * The output format, honouring `--json` as a spelling of `--format=json`.
     *
     * Two conventions grew side by side: `--json` where a command has exactly
     * two formats (`debug:module`, `debug:provides`) and `--format` where it
     * has more (`debug:graph`, `profile:report`). Both are defensible, and a
     * reader who learned one met "The --json option does not exist." on the
     * other. `--json` now works everywhere; `--format` still chooses among the
     * ones that offer a choice.
     */
    public static function format(InputInterface $input, string $default = 'text'): string
    {
        if ($input->hasOption('json') && $input->getOption('json') === true) {
            return 'json';
        }

        if (!$input->hasOption('format')) {
            return $default;
        }

        $format = self::option($input, 'format');

        return $format === '' ? $default : $format;
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
