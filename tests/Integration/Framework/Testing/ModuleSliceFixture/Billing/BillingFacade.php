<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Billing;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<BillingFactory>
 */
final class BillingFacade extends AbstractFacade
{
    public function factoryDay(): string
    {
        return $this->getFactory()->clock()->today();
    }

    public function configDay(): string
    {
        return $this->getFactory()->configClock()->today();
    }

    public function providerDay(): string
    {
        return $this->getFactory()->providedClock()->today();
    }
}
