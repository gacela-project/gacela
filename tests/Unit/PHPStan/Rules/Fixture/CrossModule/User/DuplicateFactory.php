<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

final class DuplicateFactory
{
    public function a(): ShopService
    {
        return new ShopService();
    }

    public function b(): ShopService
    {
        return new ShopService();
    }
}
