<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * Carries the attribute but not the trait, so nothing dispatches the call.
 */
#[ServiceMap(method: 'getFacade', className: MappedFacade::class)]
final class ServiceMapWithoutMagicCall
{
}
