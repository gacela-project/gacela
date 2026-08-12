<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Fixtures;

interface Decorator
{
    public function decorate(string $value): string;
}
