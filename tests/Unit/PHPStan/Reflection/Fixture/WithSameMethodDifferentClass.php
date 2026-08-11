<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Same accessor name as {@see WithServiceMap}, pointing somewhere else.
 */
#[ServiceMap(method: 'getFacade', className: MappedFactory::class)]
final class WithSameMethodDifferentClass
{
    use ServiceResolverAwareTrait;
}
