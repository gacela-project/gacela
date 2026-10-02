<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState\Cart;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class CartProvider extends AbstractProvider
{
    public const CURRENT_USER = 'CURRENT_USER';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::CURRENT_USER, static fn (): CurrentUser => new CurrentUser(Request::$user));
    }
}
