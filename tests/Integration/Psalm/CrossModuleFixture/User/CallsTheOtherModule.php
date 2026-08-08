<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ShopService;

/**
 * The crossing the call site never names: ShopService appears once, in the
 * constructor.
 */
final class CallsTheOtherModule
{
    public function __construct(
        private readonly ShopService $shop,
    ) {
    }

    public function build(): string
    {
        return $this->shop->run();
    }
}
