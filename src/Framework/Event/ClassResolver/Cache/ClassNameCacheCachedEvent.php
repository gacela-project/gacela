<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNameCacheCachedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return self::class;
    }
}