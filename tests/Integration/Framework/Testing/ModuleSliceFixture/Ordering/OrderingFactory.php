<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering\Domain\Quote;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\PricingFacade;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Money\CurrencyInterface;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\TaxRateInterface;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shipping\ShippingFacade;

/**
 * Two neighbours reached two ways, as a real module does it: Pricing through
 * the Provider, Shipping through the shorter `#[ServiceMap]` path.
 */
#[ServiceMap(method: 'getShippingFacade', className: ShippingFacade::class)]
final class OrderingFactory extends AbstractFactory
{
    use ServiceResolverAwareTrait;

    /**
     * A constructor argument, which the class resolver fills from the
     * bindings and from nothing else.
     */
    public function __construct(
        private readonly CurrencyInterface $currency,
    ) {
    }

    public function createQuote(): Quote
    {
        /** @var ShippingFacade $shipping */
        $shipping = $this->getShippingFacade();

        return new Quote($this->getPricingFacade(), $shipping, $this->getTaxRate(), $this->currency);
    }

    private function getPricingFacade(): PricingFacade
    {
        /** @var PricingFacade $facade */
        $facade = $this->getProvidedDependency(OrderingProvider::PRICING_FACADE);

        return $facade;
    }

    private function getTaxRate(): TaxRateInterface
    {
        /** @var TaxRateInterface $rate */
        $rate = $this->getProvidedDependency(OrderingProvider::TAX_RATE);

        return $rate;
    }
}
