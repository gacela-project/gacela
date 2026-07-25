<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

#[ServiceMap('getConfig', MappedFacade::class)]
final class WithPositionalServiceMap
{
    use ServiceResolverAwareTrait;
}
