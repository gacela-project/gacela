<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\AttributeWarm;

use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * Carries the same attribute as AttributeWarmService, but as an interface:
 * class_exists() rejects it while ReflectionClass accepts it, so it is the
 * fixture that shows the guard -- not reflection -- keeps the warm to classes.
 *
 * It declares no method, exactly as the class fixture does not, so the guard is
 * the only thing standing between reflection and a cached accessor for a type
 * that can never receive a call. Declaring one would have the walk skip it for
 * the other reason, and this fixture would stop proving anything.
 */
#[ServiceMap(method: 'getRepository', className: AttributeWarmRepository::class)]
interface AttributeWarmServiceInterface
{
}
