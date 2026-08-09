<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleRulesFixture\Payment;

use Gacela\Framework\AbstractFacade;

/**
 * Reaches nothing outside its own module: whatever both hosts report here, they
 * report because of {@see PaymentFactory}.
 *
 * @extends AbstractFacade<PaymentFactory>
 */
final class PaymentFacade extends AbstractFacade
{
    public function adminName(): string
    {
        return $this->getFactory()->createAdminName();
    }
}
