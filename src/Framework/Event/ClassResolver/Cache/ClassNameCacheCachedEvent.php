<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\Cache;

use Gacela\Framework\Event\GacelaEventInterface;
use Override;

use function sprintf;

final class ClassNameCacheCachedEvent implements GacelaEventInterface
{
    #[Override]
    public function toString(): string
    {
        return sprintf('%s {}', self::class);
    }
}
