<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class CacheClearedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $cacheFile,
    ) {
    }

    public function cacheFile(): string
    {
        return $this->cacheFile;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {cacheFile:"%s"}',
            self::class,
            $this->cacheFile,
        );
    }
}
