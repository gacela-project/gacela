<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Closure;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;

interface ContainerInterface
{
    /**
     * @throws ContainerKeyNotFoundException
     *
     * @return mixed
     */
    public function get(string $id);

    public function has(string $id): bool;

    /**
     * @param mixed $service
     */
    public function set(string $id, $service): void;

    public function remove(string $id): void;

    public function factory(Closure $service): object;

    public function extend(string $id, Closure $service): Closure;

    public function protect(Closure $service): object;
}
