<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Owned by the "emitted once per caller-and-method" test, which resolves twice
 * across a cache reset. It needs a fixture no other test has already reported.
 *
 * @method FakeFacade getFacade()
 */
final class FakeRepeatedCommand
{
    use ServiceResolverAwareTrait;
}
