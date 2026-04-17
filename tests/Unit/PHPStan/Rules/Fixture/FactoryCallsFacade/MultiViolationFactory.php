<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade;

use Gacela\Framework\AbstractFactory;

final class MultiViolationFactory extends AbstractFactory
{
    public function a(): ShopFacade
    {
        return new ShopFacade();
    }

    public function b(): void
    {
        $this->getFacade()->doStuff();
    }
}
