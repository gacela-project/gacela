<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

final class UsesOtherModule
{
    public function createIt(): ShopService
    {
        return new ShopService();
    }
}
