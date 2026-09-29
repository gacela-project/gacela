<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock;

interface ClockInterface
{
    public function today(): string;
}
