<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugProvides\Fixtures\BillingModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class BillingModuleProvider extends AbstractProvider
{
    public const GATEWAY = 'BILLING_GATEWAY';

    public const SHARED = 'SHARED_CLOCK';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::GATEWAY)]
    public function gateway(): string
    {
        return 'gateway';
    }

    #[Provides(self::SHARED)]
    public function clock(): string
    {
        return 'billing-clock';
    }
}
