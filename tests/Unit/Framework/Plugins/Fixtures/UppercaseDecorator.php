<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Fixtures;

final class UppercaseDecorator implements Decorator
{
    public function decorate(string $value): string
    {
        return strtoupper($value);
    }
}
