<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\DocBlockResolver;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DocBlockResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Test class for benchmarking attribute-based resolution.
 * Uses PHP attributes for performance comparison.
 */
#[ServiceMap(method: 'getFactory', className: AbstractFactory::class)]
final class DocBlockResolverAttributeBased
{
    use ServiceResolverAwareTrait;

    /**
     * Get factory through attribute-based resolution (fast path).
     * This will use searchClassOverAttributes() in DocBlockResolver.
     */
    public function getFactory(): AbstractFactory
    {
        /** @var AbstractFactory $factory */
        $factory = $this->__call('getFactory', []);

        return $factory;
    }
}
