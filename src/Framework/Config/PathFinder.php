<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class PathFinder implements PathFinderInterface
{
    public function matchingPattern(string $pattern): array
    {
        return glob($pattern, GLOB_BRACE);
    }
}
