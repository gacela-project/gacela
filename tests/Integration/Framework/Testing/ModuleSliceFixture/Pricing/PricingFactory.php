<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\Domain\PriceList;

/**
 * Final, like everything `make:module` generates -- which is what makes a
 * standalone `AbstractFactory` the only shape a double of it can take.
 *
 * @extends AbstractFactory<PricingConfig>
 */
final class PricingFactory extends AbstractFactory
{
    public function createPriceList(): PriceList
    {
        return new PriceList(['widget' => 1_000]);
    }

    public function currency(): string
    {
        return $this->getConfig()->currency();
    }

    public function catalogueName(): string
    {
        /** @var string $name */
        $name = $this->getProvidedDependency(PricingProvider::CATALOGUE_NAME);

        return $name;
    }
}
