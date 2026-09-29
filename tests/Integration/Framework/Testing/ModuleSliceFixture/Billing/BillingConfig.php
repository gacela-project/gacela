<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Billing;

use Gacela\Framework\AbstractConfig;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock\ClockInterface;

final class BillingConfig extends AbstractConfig
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function clock(): ClockInterface
    {
        return $this->clock;
    }
}
