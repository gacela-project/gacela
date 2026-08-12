<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta\Fixtures;

use Gacela\Framework\Attribute\Provides;

/**
 * Registers the id BillingProvider registers, with a different type.
 */
final class ConflictingBillingProvider
{
    #[Provides(BillingProvider::BILLING)]
    public function billing(): ReportService
    {
        return new ReportService();
    }
}
