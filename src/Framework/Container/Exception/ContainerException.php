<?php

declare(strict_types=1);

namespace Gacela\Framework\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

final class ContainerException extends Exception implements ContainerExceptionInterface
{
    public static function serviceNotExtendable(): self
    {
        return new self('The passed service is not extendable.');
    }

    public static function serviceFrozen(string $id): self
    {
        return new self("The service '{$id}' is frozen and cannot be extendable.");
    }

    public static function serviceProtected(string $id): self
    {
        return new self("The service '{$id}' is protected and cannot be extendable.");
    }
}
