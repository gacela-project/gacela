<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

abstract class SomeHostBase
{
}

/**
 * Named after a pillar but already extending something else.
 *
 * PHP has single inheritance, so "should extend AbstractFacade" is advice this
 * class cannot take. Outside Gacela the shape is ordinary -- a Laravel
 * `ServiceProvider`, an OAuth `GoogleAuthProvider` -- and these rules run
 * inside every consumer's build.
 */
final class InheritedNameFacade extends SomeHostBase
{
}
