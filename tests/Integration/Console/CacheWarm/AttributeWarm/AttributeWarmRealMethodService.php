<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\AttributeWarm;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Declares two accessors: the first is also a real method, the second is not.
 *
 * PHP calls `__call()` only for a method a class does not have, so the first
 * never reaches the resolver and an entry cached under its name is one nothing
 * can look up. The order is the fixture: skipping it by leaving the loop would
 * take the second one -- a genuine warm target -- with it.
 */
#[ServiceMap(method: 'getRepository', className: AttributeWarmRepository::class)]
#[ServiceMap(method: 'getOtherRepository', className: AttributeWarmRepository::class)]
final class AttributeWarmRealMethodService
{
    use ServiceResolverAwareTrait;

    public function getRepository(): ?AttributeWarmRepository
    {
        return null;
    }
}
