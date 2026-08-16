<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock;

/**
 * The clock a caller passes in when the date has to be predictable -- the
 * application's own test double, shipped beside the interface so consumers of
 * this app do not have to write one.
 */
final class FixedClock implements ClockInterface
{
    public function __construct(
        private readonly string $today = '2026-08-16',
    ) {
    }

    public function today(): string
    {
        return $this->today;
    }
}
