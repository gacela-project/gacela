<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Fixtures;

final class CountingDecorator implements Decorator
{
    public static int $built = 0;

    public function __construct()
    {
        ++self::$built;
    }

    public function decorate(string $value): string
    {
        return $value . '!';
    }
}
