<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNamePhpCacheCreatedEvent implements GacelaEventInterface
{
    public function __construct(
        private string $cacheDir,
    ) {
    }

    public function toString(): string
    {
        return sprintf('%s {cacheDir:"%s"}', self::class, $this->cacheDir);
    }
}
