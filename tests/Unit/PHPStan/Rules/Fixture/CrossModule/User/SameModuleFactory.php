<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\Domain\UserService;

final class SameModuleFactory
{
    public function createIt(): UserService
    {
        return new UserService();
    }
}
