<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Admin\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Admin\Shop\AdminShopService;

final class AdminUserFactory
{
    public function createIt(): AdminShopService
    {
        return new AdminShopService();
    }
}
