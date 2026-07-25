<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @psalm-suppress UndefinedClass the point of the fixture
 */
#[ServiceMap(method: 'getFacade', className: 'GacelaTest\Unit\PHPStan\Reflection\Fixture\ThisClassDoesNotExist')]
final class ServiceMapToMissingClass
{
    use ServiceResolverAwareTrait;
}
