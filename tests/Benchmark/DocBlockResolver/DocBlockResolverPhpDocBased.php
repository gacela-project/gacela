<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\DocBlockResolver;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * Test class for benchmarking PHPDoc-based resolution.
 * Uses only PHPDoc (no attributes) for performance comparison.
 *
 * @method AbstractFactory getFactory()
 */
final class DocBlockResolverPhpDocBased
{
    use DocBlockResolverAwareTrait;

    /**
     * Get factory through PHPDoc-based resolution (slower path).
     * This will use searchClassOverDocBlock() in DocBlockResolver for string parsing.
     * No @Doc attribute, so the resolver will fall through to regex-based parsing.
     */
    public function getFactory(): AbstractFactory
    {
        /** @var AbstractFactory $factory */
        $factory = $this->__call('getFactory', []);

        return $factory;
    }
}
