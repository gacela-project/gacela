<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * A skip and a failure are separate counts on purpose: a pillar class a module
 * does not have is a normal shape, and a listener alerting on
 * `failedCount() > 0` must stay quiet for it.
 */
final class CacheWarmedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly int $moduleCount,
        private readonly int $failedCount,
        private readonly int $skippedCount = 0,
    ) {
    }

    public function moduleCount(): int
    {
        return $this->moduleCount;
    }

    /**
     * Pillars that were found and blew up on resolution -- the count worth
     * alerting on.
     */
    public function failedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * Pillars a module declares but does not have. Healthy, and not an error.
     */
    public function skippedCount(): int
    {
        return $this->skippedCount;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {moduleCount:%d, failedCount:%d, skippedCount:%d}',
            self::class,
            $this->moduleCount,
            $this->failedCount,
            $this->skippedCount,
        );
    }
}
