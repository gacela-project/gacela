<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class ConfigDimensionException extends RuntimeException
{
    public static function invalidValue(string $variable, string $value): self
    {
        return new self(sprintf(
            'The config dimension "%s" has the value "%s", which cannot be used: a dimension reaches both a '
            . 'glob pattern and a cache filename, so it may contain only letters, digits, "_", "." and "-".',
            $variable,
            $value,
        ));
    }
}
