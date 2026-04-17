<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModuleOutsideRoot;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

final class SomeFactory
{
    public function createIt(): ShopService
    {
        return new ShopService();
    }
}
