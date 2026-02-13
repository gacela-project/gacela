<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Marks a facade method as cacheable with optional TTL.
 *
 * When applied to a facade method, the result will be cached and reused
 * for subsequent calls within the TTL period.
 *
 * Example:
 * ```php
 * #[Cacheable(ttl: 3600)] // Cache for 1 hour
 * public function getExpensiveData(): array
 * {
 *     return $this->getFactory()->createRepository()->fetchData();
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Cacheable
{
    /**
     * @param int $ttl Time-to-live in seconds (default: 3600 = 1 hour)
     * @param string|null $key Custom cache key (default: auto-generated from class::method::args)
     */
    public function __construct(
        public readonly int $ttl = 3600,
        public readonly ?string $key = null,
    ) {
    }
}
