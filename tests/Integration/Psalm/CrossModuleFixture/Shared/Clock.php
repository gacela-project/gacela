<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\Shared;

final class Clock
{
    public function now(): int
    {
        return 0;
    }
}
