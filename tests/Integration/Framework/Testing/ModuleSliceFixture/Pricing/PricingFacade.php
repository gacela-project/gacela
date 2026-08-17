<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing;

use Gacela\Framework\AbstractFacade;

/**
 * Not final, and that is the point rather than an oversight: a consumer that
 * type-hints a *final* Facade cannot be handed a double of it by anyone --
 * neither PHPUnit nor a hand-written subclass can produce one. A module meant
 * to be replaceable from its neighbours' tests leaves its Facade open, which is
 * what the reference application does with its Notification module.
 *
 * @extends AbstractFacade<PricingFactory>
 */
class PricingFacade extends AbstractFacade
{
    public function priceOf(string $article): int
    {
        return $this->getFactory()->createPriceList()->priceOf($article);
    }

    public function currency(): string
    {
        return $this->getFactory()->currency();
    }

    public function catalogueName(): string
    {
        return $this->getFactory()->catalogueName();
    }
}
