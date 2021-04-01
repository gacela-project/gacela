<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * @param mixed $service
     */
    public function set(string $id, $service): void;

    public function remove(string $id): void;
}
