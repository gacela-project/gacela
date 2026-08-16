<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain;

use function in_array;

/**
 * Where this business is allowed to invoice, and whether it minds.
 *
 * Built from configuration on first use, which is what `addLazy()` is for: an
 * application that never issues an invoice never reads the list.
 */
final class CountryRegistry
{
    /**
     * @param list<string> $supportedCountryCodes
     */
    public function __construct(
        private readonly array $supportedCountryCodes,
        private readonly bool $refuseUnknown,
    ) {
    }

    public function accepts(string $countryCode): bool
    {
        if (!$this->refuseUnknown) {
            return true;
        }

        return in_array($countryCode, $this->supportedCountryCodes, true);
    }

    /**
     * @return list<string>
     */
    public function supportedCountryCodes(): array
    {
        return $this->supportedCountryCodes;
    }
}
