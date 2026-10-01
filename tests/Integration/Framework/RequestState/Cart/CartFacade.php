<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState\Cart;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CartFactory>
 */
final class CartFacade extends AbstractFacade
{
    public function addItem(string $item): void
    {
        $this->getFactory()->createCart()->add($item);
    }

    /**
     * @return list<string>
     */
    public function items(): array
    {
        return $this->getFactory()->createCart()->items();
    }

    public function userName(): string
    {
        return $this->getFactory()->getCurrentUser()->name;
    }
}
