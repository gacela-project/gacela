<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\Shop;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<ShopFactory>
 */
final class ShopFacade extends AbstractFacade
{
    public function browse(): string
    {
        return $this->getFactory()->createBrowser();
    }
}
