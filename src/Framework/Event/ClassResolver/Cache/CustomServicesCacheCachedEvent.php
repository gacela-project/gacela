<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

final class CustomServicesCacheCachedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return sprintf('%s {}', self::class);
    }
}
