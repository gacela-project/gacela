<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function time;

/**
 * The one place that turns a TTL into an expiry timestamp.
 *
 * `FileCache` and `InMemoryCacheStorage` each carried their own copy of this
 * rule and disagreed about zero: one read it as "no expiry", the other computed
 * `time() + 0` and stored an entry that was expired before the write returned.
 * Two copies of a rule are two chances to get it wrong, and this is what that
 * cost. Any further backend should call this rather than restate it.
 */
final class CacheExpiry
{
    /**
     * The expiry timestamp for a lifetime in seconds, or null for an entry
     * stored without expiry.
     *
     * - `$ttl > 0`  — expires that many seconds from now.
     * - `$ttl === 0` — no expiry.
     * - `$ttl < 0`  — already expired, which is how the cache tests exercise
     *   eviction without sleeping.
     */
    public static function fromTtl(int $ttl): ?int
    {
        return $ttl === 0 ? null : time() + $ttl;
    }
}
