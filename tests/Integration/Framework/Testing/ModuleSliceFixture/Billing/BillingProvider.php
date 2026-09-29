<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Billing;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock\ClockInterface;

final class BillingProvider extends AbstractProvider
{
    public const CLOCK = 'BILLING_CLOCK';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Provides(self::CLOCK)]
    public function clock(): ClockInterface
    {
        return $this->clock;
    }
}
