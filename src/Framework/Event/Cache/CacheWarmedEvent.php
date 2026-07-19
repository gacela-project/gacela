<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class CacheWarmedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly int $moduleCount,
        private readonly int $failedCount,
    ) {
    }

    public function moduleCount(): int
    {
        return $this->moduleCount;
    }

    public function failedCount(): int
    {
        return $this->failedCount;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {moduleCount:%d, failedCount:%d}',
            self::class,
            $this->moduleCount,
            $this->failedCount,
        );
    }
}
