<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function define;
use function defined;

final class PathFinder implements PathFinderInterface
{
    /** @var array<string,array<int,string>> */
    private static array $cache = [];

    /**
     * @return string[]
     */
    public function matchingPattern(string $pattern): array
    {
        if ($pattern === '') {
            return [];
        }

        if (isset(self::$cache[$pattern])) {
            return self::$cache[$pattern];
        }

        // GLOB_BRACE is not available on some non-GNU systems (Solaris, Alpine Linux).
        // @see https://www.php.net/manual/en/function.glob.php
        if (!defined('GLOB_BRACE')) {
            define('GLOB_BRACE', 0x10);
        }

        return self::$cache[$pattern] = glob($pattern, GLOB_BRACE) ?: [];
    }
}
