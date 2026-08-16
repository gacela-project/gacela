<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax;

/**
 * Every tax rule that may apply to an invoice line.
 *
 * The stack is filled with `GacelaConfig::addPluginStack()`, and the entries
 * run in declaration order, each adding to the tax already computed -- so a
 * regional rule shipped by another config source composes with the base one
 * instead of replacing it.
 */
interface TaxCalculatorInterface
{
    /**
     * @param int $netCents the invoice net amount
     * @param int $taxSoFarCents what the calculators before this one added
     *
     * @return int the tax this rule adds, in cents
     */
    public function taxFor(int $netCents, int $taxSoFarCents, string $countryCode): int;
}
