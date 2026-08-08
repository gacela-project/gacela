<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\ShopFacade;

final class ViaFacadeFactory
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
