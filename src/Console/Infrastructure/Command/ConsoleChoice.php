<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use function implode;
use function in_array;
use function sprintf;

/**
 * Refuses an option value the command does not accept.
 *
 * `--format` used to be answered by asking whether it was `json` and treating
 * everything else as the default. That reads as a sensible fallback and is not
 * one: `--format=jsno` then prints the text report and exits 0, so the run a
 * pipeline believes produced a document is indistinguishable from a successful
 * one. `debug:graph` refused it from the start and the other four did not,
 * which is the more usual shape of this bug -- one path enforcing a rule the
 * rest of them do not.
 *
 * The message is here rather than in each command so the four say the same
 * thing, and it is the sentence `debug:graph` already used.
 */
final class ConsoleChoice
{
    /**
     * The message for a value the command does not accept, or null when it does.
     *
     * @param non-empty-list<string> $allowed
     */
    public static function unknown(string $option, string $value, array $allowed): ?string
    {
        if (in_array($value, $allowed, true)) {
            return null;
        }

        return sprintf(
            '<error>Unknown %s "%s". Use one of: %s</error>',
            $option,
            $value,
            implode(', ', $allowed),
        );
    }
}
