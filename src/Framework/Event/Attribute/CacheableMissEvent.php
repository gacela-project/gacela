<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Attribute;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * A `#[Cacheable]` method ran its callback because storage had no answer.
 *
 * Carries what the callback cost and the TTL the result was stored under,
 * which is the pair a listener needs to decide whether the caching is earning
 * anything: a miss that takes 0.2ms is noise, and the same miss repeating on
 * every request is the PHP-FPM default-storage trap that `doctor` can only
 * warn about from the configuration.
 *
 * The duration is measured around the callback alone, not around the storage
 * write -- what is worth knowing is what the cache is saving when it hits, and
 * a backend's own latency is the backend's to report.
 */
final class CacheableMissEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $className,
        private readonly string $method,
        private readonly string $cacheKey,
        private readonly float $computeMilliseconds,
        private readonly int $ttl,
    ) {
    }

    public function className(): string
    {
        return $this->className;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function cacheKey(): string
    {
        return $this->cacheKey;
    }

    public function computeMilliseconds(): float
    {
        return $this->computeMilliseconds;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {method:"%s::%s", key:"%s", computed_in:%.3fms, ttl:%d}',
            self::class,
            $this->className,
            $this->method,
            $this->cacheKey,
            $this->computeMilliseconds,
            $this->ttl,
        );
    }
}
