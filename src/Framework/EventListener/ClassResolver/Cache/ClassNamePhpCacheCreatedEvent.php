<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener\ClassResolver\Cache;

use Gacela\Framework\EventListener\GacelaEventInterface;

final class ClassNamePhpCacheCreatedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return self::class;
    }
}
