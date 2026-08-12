<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta\Fixtures;

use Gacela\Framework\Attribute\Provides;

/**
 * Every return type an editor map has no way to express.
 */
final class UnwritableTypesProvider
{
    /**
     * Read by either() so that its declared union is the narrowest type the
     * body allows -- a fixed return would let rector rewrite the union away,
     * and with it the only case covering a non-named return type.
     */
    public bool $billing = false;

    #[Provides('COMMANDS')]
    public function commands(): array
    {
        return [];
    }

    #[Provides('MAYBE_BILLING')]
    public function maybeBilling(): ?BillingService
    {
        return null;
    }

    #[Provides('EITHER')]
    public function either(): BillingService|ReportService
    {
        return $this->billing ? new BillingService() : new ReportService();
    }

    #[Provides('ITSELF')]
    public function itself(): self
    {
        return $this;
    }
}
