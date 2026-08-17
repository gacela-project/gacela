<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\PricingFacade;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\TaxRateInterface;

final class OrderingProvider extends AbstractProvider
{
    public const PRICING_FACADE = 'ORDERING_PRICING_FACADE';

    public const TAX_RATE = 'ORDERING_TAX_RATE';

    /**
     * Ordering's declared dependency on Pricing: through the locator, which is
     * the path a Facade double has to be visible on.
     */
    #[Provides(self::PRICING_FACADE)]
    public function pricingFacade(Container $container): PricingFacade
    {
        /** @var PricingFacade $facade */
        $facade = $container->getLocator()->get(PricingFacade::class);

        return $facade;
    }

    /**
     * Answered by the composition root rather than by a module, which is the
     * dependency a container binding replaces.
     */
    #[Provides(self::TAX_RATE)]
    public function taxRate(Container $container): TaxRateInterface
    {
        /** @var TaxRateInterface $rate */
        $rate = $container->get(TaxRateInterface::class);

        return $rate;
    }
}
