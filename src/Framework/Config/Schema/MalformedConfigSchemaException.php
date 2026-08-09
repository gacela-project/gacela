<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\Schema;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class MalformedConfigSchemaException extends RuntimeException
{
    public static function notAType(string $key): self
    {
        return new self(sprintf(
            'The schema entry for "%s" must be a %s, declared with ConfigType::string(), int(), float(), bool() or array().',
            $key,
            ConfigType::class,
        ));
    }

    public static function defaultOfWrongType(string $key, ConfigType $type, mixed $default): self
    {
        return new self(sprintf(
            'The default for "%s" is %s, but the key is declared %s.',
            $key,
            get_debug_type($default),
            $type->name,
        ));
    }

    public static function requiredWithDefault(string $key): self
    {
        return new self(sprintf(
            '"%s" is both required and defaulted. A default is what makes an absent key legitimate, so requiring it as well is two answers to one question.',
            $key,
        ));
    }
}
