<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\Schema;

use function sprintf;

/**
 * One configuration key that does not match what was declared for it.
 *
 * The message is built where the declaration is known, so whoever reports it --
 * `validate:config`, `doctor`, a boot-time check -- does not have to rebuild the
 * same sentence three times.
 */
final class ConfigSchemaViolation
{
    private function __construct(
        public readonly string $key,
        public readonly string $message,
    ) {
    }

    public static function missing(string $key, ConfigType $type): self
    {
        return new self($key, self::withDescription(
            sprintf('"%s" is required but missing: no config source provides it (expected %s)', $key, $type->name),
            $type,
        ));
    }

    public static function wrongType(string $key, ConfigType $type, mixed $value): self
    {
        return new self($key, self::withDescription(
            sprintf('"%s" is declared %s but is %s', $key, $type->name, $type->describeValue($value)),
            $type,
        ));
    }

    private static function withDescription(string $message, ConfigType $type): string
    {
        return $type->description === '' ? $message : $message . ' — ' . $type->description;
    }
}
