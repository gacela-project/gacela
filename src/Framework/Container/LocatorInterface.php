<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

interface LocatorInterface
{
    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function get(string $className);
}
