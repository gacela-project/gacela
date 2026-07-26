<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Names the pillar with a **fully-qualified** class name, so it resolves at the
 * docblock step and never reaches the use-statement scan.
 *
 * The two fallbacks are separate call sites reporting different strategies, and
 * `FakeCommand` only covers the use-statement one.
 *
 * @method \GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFacade getFacade()
 */
final class FakeFqcnDocBlockCommand
{
    use ServiceResolverAwareTrait;
}
