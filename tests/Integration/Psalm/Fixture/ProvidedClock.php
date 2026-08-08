<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\Fixture;

final class ProvidedClock
{
    public function now(): int
    {
        return 0;
    }
}
