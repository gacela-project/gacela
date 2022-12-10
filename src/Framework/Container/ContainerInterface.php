<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Closure;
use Gacela\Framework\Container\Exception\ContainerException;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;

interface ContainerInterface
{
    /**
     * Get the resolved value of the service.
     * Unless it is protected, in such a case it will get the raw service as it was set.
     *
     * @throws ContainerKeyNotFoundException
     *
     * @return mixed
     */
    public function get(string $id);

    /**
     * Check if a service exists.
     */
    public function has(string $id): bool;

    /**
     * Set a new service. You cannot override an existing service, but you can extend it.
     *
     * @param mixed $service
     *
     * @throws ContainerException
     */
    public function set(string $id, $service): void;

    /**
     * Remove a known service.
     */
    public function remove(string $id): void;

    /**
     * Ensure the service is returning a new instance everytime.
     */
    public function factory(Closure $service): object;

    /**
     * Extend the functionality of a service, even before it is defined.
     *
     * @throws ContainerException
     */
    public function extend(string $id, Closure $service): Closure;

    /**
     * Protect a service to be resolved. A protected service cannot be extended.
     */
    public function protect(Closure $service): object;
}
