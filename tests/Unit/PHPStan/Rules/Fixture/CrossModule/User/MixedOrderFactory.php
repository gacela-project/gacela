<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Other;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopRepository;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\ShopFacade;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\Domain\UserService;
use stdClass;

final class MixedOrderFactory
{
    /**
     * Every allowed reference kind appears before the violations, so the rule
     * must keep iterating past each skipped reference.
     *
     * @param class-string $klass
     *
     * @return list<mixed>
     */
    public function createAll(string $klass): array
    {
        $refs = [];
        $refs[] = new stdClass();
        $refs[] = new $klass();
        $refs[] = new Other();
        $refs[] = new UserService();
        $refs[] = new ShopFacade();
        $refs[] = new ShopService();
        $refs[] = new ShopService();
        $refs[] = ShopRepository::$instance;

        return $refs;
    }
}
