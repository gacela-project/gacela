<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade;

use Facade;
use Gacela\Framework\AbstractFactory;

final class MixedOrderBadFactory extends AbstractFactory
{
    /**
     * Skippable references are listed before the violations, so the rule must
     * keep iterating past each one instead of bailing out early.
     *
     * @param class-string $klass
     *
     * @return list<mixed>
     */
    public function createAll(string $klass, self $other): array
    {
        $refs = [];
        $refs[] = new $klass();
        $refs[] = new UserService();
        $refs[] = new ShopFacade();
        $refs[] = new Facade();
        $refs[] = new Sub\Facade();

        $refs[] = $this->{$klass}();
        $refs[] = $other->inner->getFacade();
        $refs[] = $other->getFacade();
        $refs[] = $this->getFacade();

        return $refs;
    }
}
