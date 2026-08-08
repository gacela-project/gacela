<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shared\Clock;

final class UsesTheSharedKernel
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
