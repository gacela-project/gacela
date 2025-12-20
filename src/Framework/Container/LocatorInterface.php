<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\Exception\ServiceNotFoundException;

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

    /**
     * Get a service from the container, throwing an exception if not found.
     * Use this when you expect the service to exist and want type-safe returns.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @throws ServiceNotFoundException
     *
     * @return T
     */
    public function getRequired(string $className): object;
}
