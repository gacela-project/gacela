<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface PathFinderInterface
{
    /**
     * @return string[]
     */
    public function matchingPattern(string $pattern): array;
}
