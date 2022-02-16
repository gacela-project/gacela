<?php

declare(strict_types=1);

namespace Gacela\Framework\Transfer;

use RuntimeException;

final class UnknownPropertyException extends RuntimeException
{
    public static function withName(string $name): self
    {
        return new self("Unknown property with name: $name");
    }
}
