<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ServiceResolver;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Test class for benchmarking PHPDoc-based resolution.
 * Uses only PHPDoc (no attributes) for performance comparison.
 *
 * @method AbstractFactory getFactory()
 */
final class DocBlockResolverPhpDocBased
{
    use ServiceResolverAwareTrait;

    /**
     * Get factory through PHPDoc-based resolution (slower path).
     * This will use searchClassOverDocBlock() in DocBlockResolver for string parsing.
     * No @ServiceMap attribute, so the resolver will fall through to regex-based parsing.
     */
    public function getFactory(): AbstractFactory
    {
        /** @var AbstractFactory $factory */
        $factory = $this->__call('getFactory', []);

        return $factory;
    }
}
