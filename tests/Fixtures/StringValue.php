<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

final class StringValue implements StringValueInterface
{
    private string $value = '';

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
