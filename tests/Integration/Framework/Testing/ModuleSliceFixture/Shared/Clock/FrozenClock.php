<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock;

final class FrozenClock implements ClockInterface
{
    public function today(): string
    {
        return 'frozen';
    }
}
