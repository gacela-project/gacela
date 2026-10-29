<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingAdminConfig;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingAdminFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingAdminFactory;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\Facade;
use GacelaTest\Integration\Framework\ServiceResolver\Reports\ReportsExportFacade;

#[ServiceMap(method: 'getAdmin', className: BillingAdminFacade::class)]
#[ServiceMap(method: 'getFactory', className: BillingAdminFactory::class)]
#[ServiceMap(method: 'getConfig', className: BillingAdminConfig::class)]
#[ServiceMap(method: 'getExport', className: ReportsExportFacade::class)]
#[ServiceMap(method: 'getFacade', className: BillingFacade::class)]
#[ServiceMap(method: 'getShortFacade', className: Facade::class)]
final class FakeAdminCommand
{
    use ServiceResolverAwareTrait;
}
