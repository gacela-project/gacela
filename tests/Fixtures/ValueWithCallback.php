<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

final readonly class ValueWithCallback
{
    public function __construct(private mixed $callback)
    {
    }

    /**
     * @param array<string, mixed> $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['callback']);
    }
}
