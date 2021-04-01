<?php

declare(strict_types=1);

namespace Gacela\Framework\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

final class ContainerException extends Exception implements ContainerExceptionInterface
{
    public static function notFound(string $id): self
    {
        return new self("The requested service '$id' was not found in the container!");
    }
}
