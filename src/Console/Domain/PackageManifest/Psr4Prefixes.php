<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PackageManifest;

use function str_starts_with;
use function strlen;

/**
 * How composer picks the psr-4 prefix that owns a class name.
 *
 * Longest match wins. Stated once because it is a decision, not a loop: a
 * project publishing both `Gacela\` and `Gacela\LaravelBridge\` gets a
 * different answer under first-match, and the two readers of this rule -- which
 * package provides a namespace, and where a generated class belongs -- would
 * disagree about the same class while both looking correct.
 *
 * First-match is not hypothetical: it is what attributed `Illuminate\Support\`
 * to the wrong package before #699 fixed it.
 */
final class Psr4Prefixes
{
    /**
     * The longest declared prefix this class name starts with, or null when
     * nothing claims it.
     *
     * Returns the prefix rather than the value behind it, so each caller reads
     * its own map -- one holds directories, the other holds package names.
     *
     * @param array<string, mixed> $byPrefix
     */
    public static function longestMatching(array $byPrefix, string $className): ?string
    {
        $best = null;

        foreach ($byPrefix as $prefix => $_value) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }

            if ($best === null || strlen($prefix) > strlen($best)) {
                $best = $prefix;
            }
        }

        return $best;
    }
}
