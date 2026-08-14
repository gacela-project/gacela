<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class CheckoutModuleProvider extends AbstractProvider
{
    public const GATEWAY = 'CHECKOUT_GATEWAY';

    public const RETRIES = 'CHECKOUT_RETRIES';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::GATEWAY)]
    public function gateway(): PaymentGatewayInterface
    {
        return new StripeGateway();
    }

    #[Provides(self::RETRIES)]
    public function retries(): int
    {
        return 3;
    }
}
