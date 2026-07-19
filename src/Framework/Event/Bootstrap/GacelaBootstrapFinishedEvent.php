<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Bootstrap;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class GacelaBootstrapFinishedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly float $durationMs,
    ) {
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {durationMs:%.3f}',
            self::class,
            $this->durationMs,
        );
    }
}
