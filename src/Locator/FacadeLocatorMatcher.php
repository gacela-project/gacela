<?php

declare(strict_types=1);

namespace Gacela\Locator;

final class FacadeLocatorMatcher implements LocatorMatcherInterface
{
    private const METHOD_PREFIX = 'facade';

    public function match(string $method): bool
    {
        return (strpos($method, self::METHOD_PREFIX) === 0);
    }
}
