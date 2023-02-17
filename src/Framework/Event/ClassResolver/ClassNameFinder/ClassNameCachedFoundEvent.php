<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNameCachedFoundEvent implements GacelaEventInterface
{
    public function __construct(
        private string $cacheKey,
        private string $className,
    ) {
    }

    public function toString(): string
    {
        return sprintf('%s {cacheKey:"%s", className:"%s"}', self::class, $this->cacheKey, $this->className);
    }
}
