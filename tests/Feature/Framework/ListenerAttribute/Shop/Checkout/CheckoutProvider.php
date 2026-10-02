<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop\Checkout;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class CheckoutProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
