<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\Cache;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ClassNameInMemoryCacheCreatedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return sprintf('%s {}', self::class);
    }
}
