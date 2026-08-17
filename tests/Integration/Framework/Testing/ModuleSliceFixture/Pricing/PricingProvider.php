<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Not final, for the same reason {@see PricingConfig} is not.
 */
class PricingProvider extends AbstractProvider
{
    public const CATALOGUE_NAME = 'PRICING_CATALOGUE_NAME';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::CATALOGUE_NAME, 'the real catalogue');
    }
}
