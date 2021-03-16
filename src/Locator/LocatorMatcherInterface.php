<?php

declare(strict_types=1);

namespace Gacela\Locator;

interface LocatorMatcherInterface
{
    public function match(string $method): bool;
}
