<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation;

use function sprintf;

/**
 * Contributed to the validator tag by `services.php` rather than by a call in
 * `gacela.php`, which is the point of loading definitions from data: the same
 * tag, filled from two places, with neither replacing the other.
 */
final class MaximumAmountValidator implements InvoiceValidatorInterface
{
    /** A single invoice this large has always been a misplaced decimal point. */
    private const int CEILING_CENTS = 100_000_000;

    public function name(): string
    {
        return 'maximum-amount';
    }

    public function reasonToRefuse(string $customerReference, int $netCents): ?string
    {
        if ($netCents <= self::CEILING_CENTS) {
            return null;
        }

        return sprintf('an invoice over %d cents needs a human', self::CEILING_CENTS);
    }
}
