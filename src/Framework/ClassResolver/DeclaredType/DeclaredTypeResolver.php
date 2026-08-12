<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DeclaredType;

use Gacela\Framework\ClassResolver\AbstractClassResolver;

/**
 * Resolves a project-declared kind, the way `FacadeResolver` resolves a Facade.
 *
 * The four pillar resolvers each hardcode their kind because each also decides
 * what a miss means. A declared kind supplies its kind at construction and gets
 * everything else -- finder rules, namespaces, caching, events -- from the
 * machinery they already share.
 *
 * A per-kind accessor trait is one method over this:
 *
 * ```php
 * public function getReader(): object
 * {
 *     return (new DeclaredTypeResolver('Reader'))->resolve($this);
 * }
 * ```
 */
final class DeclaredTypeResolver extends AbstractClassResolver
{
    public function __construct(
        private readonly string $kind,
    ) {
    }

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): ?object
    {
        return $this->doResolve($caller);
    }

    protected function getResolvableType(): string
    {
        return $this->kind;
    }
}
