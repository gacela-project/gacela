<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheWarm\TestModule;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * What `--attributes` is for: `getRepository()` is not a method this class has,
 * so `__call()` is what answers it and the resolved class name is worth having
 * on disk before the first request asks.
 */
#[ServiceMap(method: 'getRepository', className: TestRepository::class)]
final class TestModuleFactory extends AbstractFactory
{
    use ServiceResolverAwareTrait;
}
