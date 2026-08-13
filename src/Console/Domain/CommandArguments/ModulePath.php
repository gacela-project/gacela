<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\CommandArguments;

use function explode;
use function preg_match;
use function trim;

/**
 * Whether a `make:*` path can become the classes it is about to generate.
 *
 * Every segment ends up in a namespace and in a class name -- `App/user-profile`
 * becomes `namespace App\user-profile;` and `final class user-profileFacade`,
 * neither of which is PHP. The generator wrote those files anyway and reported
 * "created successfully", leaving a module that cannot be loaded and an error
 * pointing at the generated file rather than at the name that caused it.
 *
 * Kebab-case is the one people actually hit: a natural habit from ecosystems
 * where module directories are hyphenated.
 */
final class ModulePath
{
    /**
     * A PHP label: a letter or underscore, then letters, digits or underscores.
     * The high-byte range is what makes accented and non-latin names valid,
     * which they are as identifiers.
     */
    private const LABEL = '~^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$~';

    /**
     * The first segment that cannot be a namespace or class name, or null when
     * every one of them can.
     */
    public static function firstUnusableSegment(string $path): ?string
    {
        foreach (explode('/', trim($path, '/')) as $segment) {
            if (preg_match(self::LABEL, $segment) !== 1) {
                return $segment;
            }
        }

        return null;
    }
}
