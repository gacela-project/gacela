<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta\Fixtures;

use Gacela\Framework\Attribute\Provides;

final class BillingProvider
{
    public const BILLING = 'BILLING_FACADE';

    #[Provides(self::BILLING)]
    public function billing(): BillingService
    {
        return new BillingService();
    }

    /**
     * An id naming a class: the wildcard in the generated map already answers
     * it, with the class the id names rather than today's implementation.
     */
    #[Provides(ReportService::class)]
    public function report(): ReportService
    {
        return new ReportService();
    }

    /**
     * The same, for an interface -- the shape a contract is usually registered
     * under.
     */
    #[Provides(ClockInterface::class)]
    public function clock(): ClockInterface
    {
        return new class() implements ClockInterface {};
    }

    /**
     * An id typed by the interface it returns rather than a concrete class.
     */
    #[Provides('CLOCK')]
    public function namedClock(): ClockInterface
    {
        return $this->clock();
    }

    public function notProvided(): BillingService
    {
        return new BillingService();
    }
}
