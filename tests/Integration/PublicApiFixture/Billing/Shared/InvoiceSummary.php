<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Billing\Shared;

/**
 * Published by the namespace convention, with no annotation at all -- Spryker's
 * `Shared/` idea, and the 90% case the attribute should not have to carry.
 *
 * It still belongs to Billing: this is a sub-namespace the module publishes, not
 * a shared kernel outside every module.
 */
final class InvoiceSummary
{
    public function total(): int
    {
        return 100;
    }
}
