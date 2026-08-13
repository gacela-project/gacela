<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\DeclaredTypeResolverAwareTrait;

/**
 * The shapes the rules must leave alone, through Psalm's own front end.
 *
 * The analyser has host-free unit tests, and PHPStan drives the same shapes in
 * its fixture set. What is unpinned without this is Psalm's half: its storage
 * seam answers `extendsClass()` from a different source, and the static and
 * declared-kind guards read the parsed method Psalm hands over rather than
 * PHPStan's.
 *
 * @extends AbstractFacade<CleanFactory>
 */
final class CleanFacade extends AbstractFacade
{
    use DeclaredTypeResolverAwareTrait;

    public function doThing(): string
    {
        return $this->getFactory()->createThing();
    }

    /**
     * Delegating to a kind declared with `addResolvableType()`, which is
     * reached through `getResolvedType()` rather than a pillar accessor.
     */
    public function viaDeclaredKind(): ?object
    {
        return $this->getResolvedType('Exporter');
    }

    /**
     * A static method has no `$this` to delegate through, so no body it could
     * hold would satisfy the rule -- a named constructor is an ordinary shape
     * on any class.
     */
    public static function make(): self
    {
        return new self();
    }
}
