<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use function strcasecmp;
use function strlen;
use function strncasecmp;
use function trim;

/**
 * Whether a namespace covers a name, on the namespace boundary.
 *
 * On the raw prefix, `App\Pay` would silently cover `App\Payment` -- a rule
 * about one module governing another module that merely starts with the same
 * letters. Every namespace comparison in the analysis code goes through here so
 * there is one answer to that.
 */
final class NamespaceMatch
{
    public static function covers(string $namespace, string $candidate): bool
    {
        $namespace = trim($namespace, '\\');
        $candidate = trim($candidate, '\\');

        return $namespace !== ''
            && (strcasecmp($candidate, $namespace) === 0
                || strncasecmp($candidate, $namespace . '\\', strlen($namespace) + 1) === 0);
    }

    /**
     * @param list<string> $namespaces
     */
    public static function anyCovers(array $namespaces, string $candidate): bool
    {
        foreach ($namespaces as $namespace) {
            if (self::covers($namespace, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
