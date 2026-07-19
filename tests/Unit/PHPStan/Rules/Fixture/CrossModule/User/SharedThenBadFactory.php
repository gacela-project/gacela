<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared\Clock;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

final class SharedThenBadFactory
{
    public function createIt(): ShopService
    {
        new Clock();

        return new ShopService();
    }
}
