<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use function sprintf;

final class BytesFormatter
{
    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return sprintf('%d B', $bytes);
        }

        if ($bytes < 1048576) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return sprintf('%.2f MB', $bytes / 1048576);
    }
}
