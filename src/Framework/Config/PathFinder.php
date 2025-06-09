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

        $this->ensureGlobBraceIsDefined();

        return self::$cache[$pattern] = glob($pattern, GLOB_BRACE) ?: [];
    }

    /**
     * Note: The GLOB_BRACE flag is not available on some non-GNU systems, like Solaris or Alpine Linux.
     *
     * @see https://www.php.net/manual/en/function.glob.php
     */
    private function ensureGlobBraceIsDefined(): void
    {
        if (!defined('GLOB_BRACE')) {
            define('GLOB_BRACE', 0x10);
        }
    }
}
