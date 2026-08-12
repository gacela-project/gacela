<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderService\Checkout;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class CheckoutProvider extends AbstractProvider
{
    public const LABEL = 'LABEL';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::LABEL, ['checkout']);
    }
}
