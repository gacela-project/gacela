<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ClassNameCachedFoundEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $cacheKey,
        private readonly string $className,
    ) {
    }

    public function cacheKey(): string
    {
        return $this->cacheKey;
    }

    public function className(): string
    {
        return $this->className;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {cacheKey:"%s", className:"%s"}',
            self::class,
            $this->cacheKey,
            $this->className,
        );
    }
}
