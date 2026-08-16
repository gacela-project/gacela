<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Packaging;

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\DigitalServicesSurcharge;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxCalculatorInterface;

/**
 * The tax rules only a production ledger owes. Contributed to a stack this
 * class did not declare -- repeated `addPluginStack()` calls append, so the
 * base rule keeps running and this one composes with it.
 */
final class StrictTaxRules
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addPluginStack(TaxCalculatorInterface::class, [DigitalServicesSurcharge::class]);
    }
}
