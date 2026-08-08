<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use PhpParser\Node\Name;

use function is_string;
use function ltrim;

/**
 * The fully qualified name behind a name written in source.
 *
 * The hosts hand over differently resolved trees. PHPStan rewrites names in
 * place, so `toString()` is already qualified. Psalm leaves the source text
 * alone and puts the qualified form on the node as a `resolvedName` attribute,
 * so `toString()` there is whatever was typed -- `ShopService` for an imported
 * class.
 *
 * Reading only `toString()` therefore worked under PHPStan and silently matched
 * nothing under Psalm: every module name carries a namespace, and a bare short
 * name belongs to none.
 *
 * The attribute is a string as Psalm writes it and a {@see Name} as
 * php-parser's own `NameResolver` writes it, so both are read.
 */
final class ResolvedName
{
    public static function of(Name $name): string
    {
        // A leading separator is legal and means the same class; module names
        // carry none, so it has to go whichever form the name arrived in.
        return ltrim(self::text($name->getAttribute('resolvedName')) ?? $name->toString(), '\\');
    }

    /**
     * Taken as a parameter rather than assigned to a local: psalm reports every
     * assignment of a mixed, and the `@var mixed` that answers it is a tag
     * rector then strips as useless.
     */
    private static function text(mixed $resolved): ?string
    {
        if (is_string($resolved)) {
            return $resolved;
        }

        return $resolved instanceof Name ? $resolved->toString() : null;
    }
}
