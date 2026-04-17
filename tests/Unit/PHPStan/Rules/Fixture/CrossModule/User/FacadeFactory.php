<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\ShopFacade;

final class FacadeFactory
{
    public function createIt(): ShopFacade
    {
        return new ShopFacade();
    }
}
