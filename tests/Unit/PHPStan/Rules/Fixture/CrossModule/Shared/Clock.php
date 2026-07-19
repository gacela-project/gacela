<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared;

final class Clock
{
    public function now(): int
    {
        return 0;
    }
}
