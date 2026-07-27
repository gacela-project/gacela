<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\AttributeWarm;

use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * Carries the same attribute as AttributeWarmService, but as an interface:
 * class_exists() rejects it while ReflectionClass accepts it, so it is the
 * fixture that shows the guard -- not reflection -- keeps the warm to classes.
 */
#[ServiceMap(method: 'getRepository', className: AttributeWarmRepository::class)]
interface AttributeWarmServiceInterface
{
    public function getRepository(): void;
}
