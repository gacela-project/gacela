<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState\Cart;

use Gacela\Framework\AbstractFactory;

final class CartFactory extends AbstractFactory
{
    public function createCart(): Cart
    {
        return $this->singleton('cart', static fn (): Cart => new Cart());
    }

    public function getCurrentUser(): CurrentUser
    {
        /** @var CurrentUser */
        return $this->getProvidedDependency(CartProvider::CURRENT_USER);
    }
}
