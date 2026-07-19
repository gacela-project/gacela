<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Config;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ConfigInitializedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly int $keyCount,
    ) {
    }

    public function keyCount(): int
    {
        return $this->keyCount;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {keyCount:%d}',
            self::class,
            $this->keyCount,
        );
    }
}
