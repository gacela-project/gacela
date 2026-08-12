<?php

declare(strict_types=1);

namespace Gacela\Framework\Dto\Schema;

/**
 * What one property of a declared shape is.
 *
 * The same declaration voice as `ConfigType`, refined the same way, for a
 * different destination: a config type is read to *check* a value, this one is
 * read to *write* a class. So it carries the php type it generates rather than
 * a predicate that accepts one.
 *
 * Immutable: each refinement returns a new type, so a shape a package declared
 * cannot be altered from a distance by whoever else holds it.
 */
final class DtoType
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
     * What the property is for, in the words of whoever declared it. It travels
     * into the generated docblock, which is the only place a reader of the
     * generated class can learn it.
     */
    public function describe(string $description): self
    {
        return new self($this->name, $this->isRequired, $this->hasDefault, $this->default, $description);
    }

    /**
     * Whether two declarations of one property say the same thing.
     *
     * The description is deliberately not compared: it is prose about the
     * property, not part of its shape, and refusing a redeclaration over a
     * reworded sentence would make the merge rule about wording.
     */
    public function isSameShapeAs(self $other): bool
    {
        return $this->name === $other->name
            && $this->isRequired === $other->isRequired
            && $this->hasDefault === $other->hasDefault
            && $this->default === $other->default;
    }

    /**
     * The type as it is written in generated code.
     */
    public function phpType(): string
    {
        return $this->name;
    }
}
