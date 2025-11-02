<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\DocBlockResolver;

use Gacela\Framework\DocBlockResolver\DocBlockResolverCache;
use Gacela\Framework\Gacela;

/**
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 */
final class DocBlockResolverBench
{
    public function setUp(): void
    {
        // Bootstrap Gacela on first run
        Gacela::bootstrap(__DIR__);

        // Clear cache before each benchmark to measure actual resolution time
        DocBlockResolverCache::resetCache();
    }

    public function tearDown(): void
    {
        // Clear cache after each benchmark
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
