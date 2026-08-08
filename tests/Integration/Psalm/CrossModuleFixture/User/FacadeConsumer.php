<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\ShopFacade;

/**
 * Named so it does not itself end in a pillar suffix -- the suffix rule runs
 * over these fixtures too.
 */
final class FacadeConsumer
{
    public function __construct(
        private readonly ShopFacade $shop,
    ) {
    }

    public function build(): string
    {
        return $this->shop->browse();
    }
}
