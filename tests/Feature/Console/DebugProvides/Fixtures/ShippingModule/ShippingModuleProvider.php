<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugProvides\Fixtures\ShippingModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\Console\DebugProvides\Fixtures\BillingModule\BillingModuleProvider;

final class ShippingModuleProvider extends AbstractProvider
{
    public const CARRIER = 'SHIPPING_CARRIER';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::CARRIER)]
    public function carrier(): string
    {
        return 'carrier';
    }

    /**
     * The same id the billing module declares. Not a collision: each module
     * resolves through its own container.
     */
    #[Provides(BillingModuleProvider::SHARED)]
    public function clock(): string
    {
        return 'shipping-clock';
    }
}
