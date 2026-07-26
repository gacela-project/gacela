<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * `#[ServiceMap]` is IS_REPEATABLE, and a module normally maps more than one
 * pillar, so scanning must not stop at the first attribute that does not match.
 */
#[ServiceMap(method: 'getFacade', className: MappedFacade::class)]
#[ServiceMap(method: 'getFactory', className: MappedFactory::class)]
final class WithRepeatedServiceMap
{
    use ServiceResolverAwareTrait;
}
