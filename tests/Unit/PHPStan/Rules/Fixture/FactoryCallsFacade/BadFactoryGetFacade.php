<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade;

use Gacela\Framework\AbstractFactory;

final class BadFactoryGetFacade extends AbstractFactory
{
    public function doSomething(): void
    {
        $this->getFacade()->doStuff();
    }
}
