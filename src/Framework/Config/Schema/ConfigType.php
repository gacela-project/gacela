<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\Schema;

use function get_debug_type;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * What one configuration key is expected to be.
 *
 * Every call site already knows: `getInt('retries')` says so, and so does the
 * code that reads it. What nothing knew until now is whether the environment
 * about to boot actually carries that key, in that shape -- so the expectation
 * is written down once, where a command can read it before anything runs.
 *
 * Immutable: each refinement returns a new type, so a declaration cannot be
 * changed from a distance by whoever else holds it.
 */
final class ConfigType
{
    public const STRING = 'string';

    public const INT = 'int';

    public const FLOAT = 'float';

    public const BOOL = 'bool';

    public const ARRAY = 'array';

    private function __construct(
        public readonly string $name,
        public readonly bool $isRequired = false,
        public readonly bool $hasDefault = false,
        public readonly mixed $default = null,
        public readonly string $description = '',
    ) {
    }

    public static function string(): self
    {
        return new self(self::STRING);
    }

    public static function int(): self
    {
        return new self(self::INT);
    }

    public static function float(): self
    {
        return new self(self::FLOAT);
    }

    public static function bool(): self
    {
        return new self(self::BOOL);
    }

    public static function array(): self
    {
        return new self(self::ARRAY);
    }

    public function required(): self
    {
        return new self($this->name, true, $this->hasDefault, $this->default, $this->description);
    }

    public function default(mixed $default): self
    {
        return new self($this->name, $this->isRequired, true, $default, $this->description);
    }

    /**
     * What the key is for, in the words of whoever declared it -- it travels
     * into the violation, where "wrong type" alone leaves the reader guessing.
     */
    public function describe(string $description): self
    {
        return new self($this->name, $this->isRequired, $this->hasDefault, $this->default, $description);
    }

    public function accepts(mixed $value): bool
    {
        return match ($this->name) {
            self::STRING => is_string($value),
            self::INT => is_int($value),
            // An int is a legitimate float: `timeout: 5` in a config file is
            // about the value, not about php's literal syntax.
            self::FLOAT => is_float($value) || is_int($value),
            self::BOOL => is_bool($value),
            default => is_array($value),
        };
    }

    public function describeValue(mixed $value): string
    {
        return get_debug_type($value);
    }
}
