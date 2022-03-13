<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function is_array;

final class PathFinder implements PathFinderInterface
{
    /**
     * @return string[]
     */
    public function matchingPattern(string $pattern): array
    {
        $glob = glob($pattern, GLOB_BRACE);

        return is_array($glob) ? $glob : [];
    }
}
