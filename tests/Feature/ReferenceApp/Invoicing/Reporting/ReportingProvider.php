<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;

/**
 * @extends AbstractProvider<ReportingConfig>
 */
final class ReportingProvider extends AbstractProvider
{
    public const BILLING_FACADE = 'REPORTING_BILLING_FACADE';

    public const CUSTOMER_FACADE = 'REPORTING_CUSTOMER_FACADE';

    #[Provides(self::BILLING_FACADE)]
    public function billingFacade(Container $container): BillingFacade
    {
        /** @var BillingFacade $facade */
        $facade = $container->getLocator()->get(BillingFacade::class);

        return $facade;
    }

    #[Provides(self::CUSTOMER_FACADE)]
    public function customerFacade(Container $container): CustomerFacade
    {
        /** @var CustomerFacade $facade */
        $facade = $container->getLocator()->get(CustomerFacade::class);

        return $facade;
    }
}
