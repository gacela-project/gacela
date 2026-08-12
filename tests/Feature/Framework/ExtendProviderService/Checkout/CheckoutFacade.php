<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderService\Checkout;

use Gacela\Framework\AbstractFacade;

/**
 * @method CheckoutFactory getFactory()
 */
final class CheckoutFacade extends AbstractFacade
{
    public function label(): string
    {
        return $this->getFactory()->label();
    }
}
