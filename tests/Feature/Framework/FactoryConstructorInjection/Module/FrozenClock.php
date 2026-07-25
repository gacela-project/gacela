<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FactoryConstructorInjection\Module;

final class FrozenClock implements ClockInterface
{
    public function now(): string
    {
        return '2026-01-01';
    }
}
