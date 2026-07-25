<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\AttributeWarm;

use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * The declaration order is the fixture: warmAttributeCache() walks the public
 * methods in order, and the two methods before `getRepository()` are the ones
 * it has to leave alone. A magic method must be skipped without ending the
 * scan, and a plain method that matches none of the resolvable prefixes must
 * not be resolved at all -- resolving it would throw and abort the warm.
 */
#[ServiceMap(method: 'getRepository', className: AttributeWarmRepository::class)]
final class AttributeWarmService
{
    public function __invoke(): void
    {
    }

    public function doSomething(): void
    {
    }

    public function getRepository(): void
    {
    }
}
