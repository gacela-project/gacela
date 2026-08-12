<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\IdeMeta\Report;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\Console\IdeMeta\Billing\BillingProvider;

final class ReportProvider extends AbstractProvider
{
    public const REPORT_RENDERER = 'REPORT_RENDERER';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::REPORT_RENDERER)]
    public function reportRenderer(): ReportRenderer
    {
        return new ReportRenderer();
    }

    /**
     * The id BillingProvider also registers, with a different type. Each module
     * reads its own container, so both are correct where they live -- and one
     * application-wide answer would be wrong in one of them.
     */
    #[Provides(BillingProvider::SHARED_ID)]
    public function shared(): ReportRenderer
    {
        return new ReportRenderer();
    }
}
