<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ServiceResolver\Fixtures;

use Gacela\Framework\ServiceResolver\ServiceMap;

#[ServiceMap(method: 'getFacade', className: FirstService::class)]
#[ServiceMap(method: 'getConfig', className: SecondService::class)]
// A repeat of an accessor already declared: the runtime returns the first, so
// this one names a call that never happens.
#[ServiceMap(method: 'getFacade', className: SecondService::class)]
class ClassWithAccessors
{
}
