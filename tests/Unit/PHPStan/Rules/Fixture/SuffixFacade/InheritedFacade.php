<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\SuffixFacade;

abstract class SomeOtherBase
{
}

/**
 * Named after a pillar but already extending something else.
 *
 * PHP has single inheritance, so "should extend AbstractFacade" is advice this
 * class cannot take -- the same reason interfaces, traits and enums go
 * unreported. Outside Gacela this shape is ordinary: an OAuth
 * `GoogleAuthProvider extends AbstractOAuthProvider` has nothing to do with the
 * framework, and this rule runs inside every consumer's build.
 */
final class InheritedFacade extends SomeOtherBase
{
}
