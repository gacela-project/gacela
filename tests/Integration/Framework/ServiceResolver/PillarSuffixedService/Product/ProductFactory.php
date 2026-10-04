<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\PillarSuffixedService\Product;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method PriceConfig getPriceConfig()
 *
 * @extends AbstractFactory<ProductConfig>
 */
#[ServiceMap(method: 'getPriceConfig', className: PriceConfig::class)]
final class ProductFactory extends AbstractFactory
{
    use ServiceResolverAwareTrait;

    public function config(): ProductConfig
    {
        return $this->getConfig();
    }
}
