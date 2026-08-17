<?php

declare(strict_types=1);

namespace Gacela\Console\Testing;

use RuntimeException;

use function sprintf;

/**
 * The test's own setup being wrong, rather than a boundary being broken.
 *
 * Kept apart from an assertion failure on purpose: a missing rules file
 * reported as a violation sends the reader to look at the application, which is
 * the one place the problem is not.
 */
final class ModuleAssertionException extends RuntimeException
{
    public static function unreadableFile(string $path, string $label): self
    {
        return new self(sprintf('Cannot read the %s: "%s".', $label, $path));
    }

    public static function invalidJson(string $path, string $label, string $reason): self
    {
        return new self(sprintf('The %s "%s" is not valid JSON: %s', $label, $path, $reason));
    }

    public static function notAListOfEntries(string $path, string $label): self
    {
        return new self(sprintf('The %s "%s" must contain a JSON array.', $label, $path));
    }
}
