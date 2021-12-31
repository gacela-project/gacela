<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

final class ConfigException extends RuntimeException
{
    public static function keyNotFound(string $key, string $class): self
    {
        return new self(sprintf(
            'Could not find config key "%s" in "%s"',
            $key,
            $class
        ));
    }
}
