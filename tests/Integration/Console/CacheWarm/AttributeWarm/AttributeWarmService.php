<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\AttributeWarm;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * The shape warming exists for: an accessor declared by the attribute and *not*
 * declared as a method, so `__call()` is what answers `getRepository()` and the
 * warmed entry is one it will look up.
 *
 * The two other methods are here to be left alone — neither is an accessor the
 * attribute declares, and warming has no business resolving them.
 */
#[ServiceMap(method: 'getRepository', className: AttributeWarmRepository::class)]
final class AttributeWarmService
{
    use ServiceResolverAwareTrait;

    public function __invoke(): void
    {
    }

    public function doSomething(): void
    {
    }
}
