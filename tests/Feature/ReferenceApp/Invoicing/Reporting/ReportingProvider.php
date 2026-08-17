<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;

/**
 * @extends AbstractProvider<ReportingConfig>
 */
final class ReportingProvider extends AbstractProvider
{
    public const BILLING_FACADE = 'REPORTING_BILLING_FACADE';

    /**
     * The whole of Reporting's declared dependency: the ledger a revenue report
     * is a read of. The customer names it also reads come through the
     * `#[ServiceMap]` accessor on {@see ReportingFactory}, which is the shorter
     * path for a lookup that decorates the report rather than making it.
     */
    #[Provides(self::BILLING_FACADE)]
    public function billingFacade(Container $container): BillingFacade
    {
        /** @var BillingFacade $facade */
        $facade = $container->getLocator()->get(BillingFacade::class);

        return $facade;
    }
}
