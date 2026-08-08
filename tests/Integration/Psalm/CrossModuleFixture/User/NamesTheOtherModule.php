<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ShopService;

/**
 * The crossing written at the call site.
 */
final class NamesTheOtherModule
{
    public function build(): ShopService
    {
        return new ShopService();
    }
}
