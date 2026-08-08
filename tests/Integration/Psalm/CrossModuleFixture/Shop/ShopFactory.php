<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\Shop;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class ShopFactory extends AbstractFactory
{
    public function createBrowser(): string
    {
        return 'browsing';
    }
}
