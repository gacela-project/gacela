<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Billing;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock\ClockInterface;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Clock\Timezone;

/**
 * Every pillar of this module takes the clock `gacela.php` registers in its
 * constructor, so each one is a place a slice has to be able to replace it.
 * The Factory also takes the timezone, a concrete class `gacela.php` builds.
 *
 * @extends AbstractFactory<BillingConfig>
 */
final class BillingFactory extends AbstractFactory
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly Timezone $timezone,
    ) {
    }

    public function timezone(): Timezone
    {
        return $this->timezone;
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
