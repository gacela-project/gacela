<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function time;

final class CacheExpiry
{
    public static function fromTtl(int $ttl): ?int
    {
        if ($ttl === 0) {
            return null;
        }

        $now = time();

        return $ttl > PHP_INT_MAX - $now ? null : $now + $ttl;
    }
}
