<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ServiceResolver;

use Gacela\Framework\Gacela;
use Gacela\Framework\ServiceResolver\DocBlockResolverCache;

/**
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 */
final class DocBlockResolverBench
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);

        DocBlockResolverCache::resetCache();
    }

    public function tearDown(): void
    {
        DocBlockResolverCache::resetCache();
    }

    /**
     * Benchmark attribute-based resolution.
     * Tests the fast path through searchClassOverAttributes().
     */
    public function bench_attribute_based_resolution(): void
    {
        $resolver = new DocBlockResolverAttributeBased();
        $resolver->getFactory();
    }

    /**
     * Benchmark PHPDoc-based resolution.
     * Tests the slower path through searchClassOverDocBlock() and string parsing.
     */
    public function bench_phpdoc_based_resolution(): void
    {
        $resolver = new DocBlockResolverPhpDocBased();
        $resolver->getFactory();
    }

    /**
     * Benchmark attribute resolution with repeated calls (tests caching).
     */
    public function bench_attribute_resolution_repeated(): void
    {
        $resolver = new DocBlockResolverAttributeBased();
        for ($i = 0; $i < 10; ++$i) {
            $resolver->getFactory();
        }
    }

    /**
     * Benchmark PHPDoc resolution with repeated calls (tests caching).
     */
    public function bench_phpdoc_resolution_repeated(): void
    {
        $resolver = new DocBlockResolverPhpDocBased();
        for ($i = 0; $i < 10; ++$i) {
            $resolver->getFactory();
        }
    }
}
