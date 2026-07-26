<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Names its pillar **unqualified**, so resolution falls through the docblock
 * step to the use-statement scan.
 *
 * Owned solely by the deprecation test. The notice is emitted once per
 * caller-and-method per process, so a fixture shared with any other test class
 * would make this assertion depend on suite order -- which is randomised.
 *
 * @method FakeFacade getFacade()
 */
final class FakeUseStatementCommand
{
    use ServiceResolverAwareTrait;
}
