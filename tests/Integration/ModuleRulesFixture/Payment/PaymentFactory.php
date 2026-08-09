<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleRulesFixture\Payment;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\ModuleRulesFixture\Admin\AdminFacade;

/**
 * The forbidden edge, written the way a real one is: through the other module's
 * Facade, which every other rule in this project considers correct. Only the
 * declared rules say this particular pair must not happen.
 */
final class PaymentFactory extends AbstractFactory
{
    public function createAdminName(): string
    {
        return (new AdminFacade())->name();
    }
}
