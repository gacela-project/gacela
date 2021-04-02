<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

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
}
