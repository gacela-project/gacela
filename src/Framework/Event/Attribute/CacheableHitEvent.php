<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Attribute;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * A `#[Cacheable]` method answered from storage without running its callback.
 *
 * Paired with {@see CacheableMissEvent}, this is what makes a hit rate
 * measurable. Until both existed the attribute cached silently: whether it
 * ever returned a stored value was not observable from inside the application
 * at all.
 *
 * That matters most where the caching is doing nothing. `doctor` reports a
 * `#[Cacheable]` method left on the default storage, which dies with the
 * process -- so under PHP-FPM an hour's TTL is recomputed on every request --
 * but only as a static finding about configuration. A run that is *actually*
 * missing every time says so here, in production, where the storage is
 * whatever the deployment really wired up.
 */
final class CacheableHitEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $className,
        private readonly string $method,
        private readonly string $cacheKey,
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

    public function toString(): string
    {
        return sprintf(
            '%s {method:"%s::%s", key:"%s"}',
            self::class,
            $this->className,
            $this->method,
            $this->cacheKey,
        );
    }
}
