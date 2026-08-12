<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta\Fixtures;

use Gacela\Framework\Attribute\Provides;

/**
 * Registers the id BillingProvider registers, with the same type.
 */
final class AgreeingBillingProvider
{
    #[Provides(BillingProvider::BILLING)]
    public function billing(): BillingService
    {
        return new BillingService();
    }
}
