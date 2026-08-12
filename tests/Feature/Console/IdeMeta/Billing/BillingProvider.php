<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\IdeMeta\Billing;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class BillingProvider extends AbstractProvider
{
    public const INVOICE_SENDER = 'INVOICE_SENDER';

    public const SHARED_ID = 'SHARED_ID';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::INVOICE_SENDER)]
    public function invoiceSender(): InvoiceSender
    {
        return new InvoiceSender();
    }

    #[Provides(self::SHARED_ID)]
    public function shared(): InvoiceSender
    {
        return new InvoiceSender();
    }

    #[Provides('COMMANDS')]
    public function commands(): array
    {
        return [];
    }
}
