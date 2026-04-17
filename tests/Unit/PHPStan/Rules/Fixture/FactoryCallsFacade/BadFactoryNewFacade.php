<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade;

use Gacela\Framework\AbstractFactory;

final class BadFactoryNewFacade extends AbstractFactory
{
    public function createSomething(): ShopFacade
    {
        return new ShopFacade();
    }
}
