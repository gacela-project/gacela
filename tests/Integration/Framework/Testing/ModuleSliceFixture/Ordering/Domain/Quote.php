<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering\Domain;

use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\PricingFacade;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Money\CurrencyInterface;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\TaxRateInterface;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shipping\ShippingFacade;

use function sprintf;

final class Quote
{
    public function __construct(
        private readonly PricingFacade $pricing,
        private readonly ShippingFacade $shipping,
        private readonly TaxRateInterface $taxRate,
        private readonly CurrencyInterface $currency,
    ) {
    }

    /**
     * Every number the slice can replace, in one string: what the article
     * costs, what shipping it costs, and the rate applied on top.
     */
    public function describe(string $article): string
    {
        return sprintf(
            'price:%d shipping:%d tax:%d currency:%s',
            $this->pricing->priceOf($article),
            $this->shipping->costOf($article),
            $this->taxRate->basisPoints(),
            $this->currency->code(),
        );
    }
}
