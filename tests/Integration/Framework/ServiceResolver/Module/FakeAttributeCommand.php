<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Declares its pillar with the attribute rather than a `@method` docblock, so
 * it resolves through the primary path and must **not** raise a deprecation.
 */
#[ServiceMap(method: 'getFacade', className: FakeFacade::class)]
final class FakeAttributeCommand
{
    use ServiceResolverAwareTrait;
}
