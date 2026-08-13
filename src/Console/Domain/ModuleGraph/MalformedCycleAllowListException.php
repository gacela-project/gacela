<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use RuntimeException;

use function implode;
use function in_array;
use function sprintf;

final class MalformedCycleAllowListException extends RuntimeException
{
    /**
     * The file's shape, before any entry is judged.
     *
     * `--rules` on this same command takes an object with a `rules` key, so
     * reaching for `{"cycles": […]}` here is the natural mistake — and its
     * values were then walked as entries, so the report blamed "entry #0" for a
     * file whose shape was wrong. A correct entry that was never wrapped in the
     * array lands here too, and is worth its own sentence: the fix is a pair of
     * brackets.
     *
     * @param list<array-key> $keys
     */
    public static function notAListOfEntries(array $keys): self
    {
        $looksLikeOneEntry = in_array('modules', $keys, true) || in_array('reason', $keys, true);

        return new self(sprintf(
            'The allowed-cycles file must be a JSON array of entries, each with "modules" and "reason" — found an object with keys: %s.%s',
            implode(', ', $keys),
            $looksLikeOneEntry
                ? ' That looks like a single entry: wrap it in [ ].'
                : ' Note that --rules takes an object, and this one takes the array directly.',
        ));
    }

    public static function entryIsNotAnObject(int $position): self
    {
        return new self(sprintf('Allowed-cycle entry #%d must be an object with "modules" and "reason".', $position));
    }

    public static function missingModules(int $position): self
    {
        return new self(sprintf('Allowed-cycle entry #%d must list at least two "modules".', $position));
    }

    public static function missingReason(int $position): self
    {
        return new self(sprintf(
            'Allowed-cycle entry #%d needs a non-empty "reason": an allowance nobody justified is indistinguishable from a cycle nobody noticed.',
            $position,
        ));
    }
}
