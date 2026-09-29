<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Billing;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock\ClockInterface;

/**
 * Every pillar of this module takes the clock `gacela.php` registers in its
 * constructor, so each one is a place a slice has to be able to replace it.
 *
 * @extends AbstractFactory<BillingConfig>
 */
final class BillingFactory extends AbstractFactory
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function clock(): ClockInterface
    {
        return $this->clock;
    }

    public function configClock(): ClockInterface
    {
        return $this->getConfig()->clock();
    }

    public function providedClock(): ClockInterface
    {
        /** @var ClockInterface $clock */
        $clock = $this->getProvidedDependency(BillingProvider::CLOCK);

        return $clock;
    }
}
