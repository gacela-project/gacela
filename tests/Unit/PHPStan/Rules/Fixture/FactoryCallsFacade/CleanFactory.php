<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade;

use Gacela\Framework\AbstractFactory;

final class CleanFactory extends AbstractFactory
{
    public function createUserService(object $service): \GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\UserService
    {
        $service->getFacade();

        return new UserService();
    }
}
