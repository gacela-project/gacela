<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing;

use Gacela\Framework\AbstractConfig;

/**
 * Every value this module reads, named once and typed here.
 *
 * The typed getters are the point: a key that is declared `int` in the schema
 * and written as a string in one environment fails on this line, with the key
 * in the message, instead of somewhere downstream doing arithmetic on a string.
 */
final class BillingConfig extends AbstractConfig
{
    public function currency(): string
    {
        return $this->getString('billing.currency');
    }

    public function retentionYears(): int
    {
        return $this->getInt('billing.retention_years');
    }

    public function refusesUnknownCountries(): bool
    {
        return $this->getBool('billing.refuse_unknown_countries');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function supportedCountries(): array
    {
        return $this->getArray('billing.supported_countries');
    }
}
