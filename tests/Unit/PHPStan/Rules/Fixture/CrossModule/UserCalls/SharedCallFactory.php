<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared\Clock;

final class SharedCallFactory
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }

    public function build(): int
    {
        return $this->clock->now();
    }
}
