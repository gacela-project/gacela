<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface PathFinderInterface
{
    public function matchingPattern(string $pattern): array;
}
