<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function define;
use function defined;
use function is_array;

final class PathFinder implements PathFinderInterface
{
    /**
     * @return string[]
     */
    public function matchingPattern(string $pattern): array
    {
        $this->ensureGlobBraceIsDefined();

        /** @psalm-suppress UndefinedConstant, MixedArgument */
        $glob = glob($pattern, GLOB_BRACE);

        return is_array($glob) ? $glob : [];
    }

    /**
     * Note: The GLOB_BRACE flag is not available on some non GNU systems, like Solaris or Alpine Linux.
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
