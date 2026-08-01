<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\AbstractFactory\SharedApp;

final class AlphaClockHolder
{
    public function __construct(public readonly ClockInterface $clock)
    {
    }
}
