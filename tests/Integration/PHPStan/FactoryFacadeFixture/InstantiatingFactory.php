<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\FactoryFacadeFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\PHPStan\FactoryFacadeFixture\Billing\BillingFacade;

/**
 * Reported: builds another module's Facade instead of declaring it in the Provider.
 *
 * @extends AbstractFactory<AbstractConfig>
 */
final class InstantiatingFactory extends AbstractFactory
{
    public function createBilling(): BillingFacade
    {
        return new BillingFacade();
    }
}
