<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNameCachedFoundEvent implements GacelaEventInterface
{
    public function __construct(
        private string $className,
    ) {
    }

    public function toString(): string
    {
        return sprintf('%s - %s', self::class, $this->className);
    }
}
