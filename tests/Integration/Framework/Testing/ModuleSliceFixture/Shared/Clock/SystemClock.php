<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock;

final class SystemClock implements ClockInterface
{
    public function today(): string
    {
        return 'system';
    }
}
