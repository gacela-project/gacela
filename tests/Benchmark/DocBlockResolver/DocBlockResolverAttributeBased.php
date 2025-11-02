<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\DocBlockResolver;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DocBlockResolver\Doc;
use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * Test class for benchmarking attribute-based resolution.
 * Uses PHP attributes for performance comparison.
 */
#[Doc(method: 'getFactory', className: AbstractFactory::class)]
final class DocBlockResolverAttributeBased
{
    use DocBlockResolverAwareTrait;

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
