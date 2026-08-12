<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DeclaredType\DeclaredTypeResolver;

use function array_key_exists;

/**
 * Reach this module's class of a declared kind, the way
 * {@see ConfigResolverAwareTrait} reaches its Config.
 *
 * The pillars each get a named accessor because there are four of them and the
 * names are part of the vocabulary. A declared kind names itself, so the kind
 * is the argument -- and a project that wants `getReader()` writes one method
 * over this one.
 */
trait DeclaredTypeResolverAwareTrait
{
    /** @var array<string, object|null> */
    private array $resolvedDeclaredTypes = [];

    public function getResolvedType(string $kind): ?object
    {
        if (!array_key_exists($kind, $this->resolvedDeclaredTypes)) {
            $this->resolvedDeclaredTypes[$kind] = (new DeclaredTypeResolver($kind))->resolve($this);
        }

        return $this->resolvedDeclaredTypes[$kind];
    }
}
