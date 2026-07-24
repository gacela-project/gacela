<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Factory\Make;

final class Widget
{
    public function __construct(
        public readonly string $name = 'autowired',
    ) {
    }
}
