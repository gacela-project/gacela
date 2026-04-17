<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

final class StaticCallFactory
{
    public function doIt(): void
    {
        ShopService::doStuff();
    }
}
