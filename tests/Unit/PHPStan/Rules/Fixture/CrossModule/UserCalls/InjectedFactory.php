<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

/**
 * The crossing the name-matching rule cannot see: ShopService is written once,
 * in a constructor type-hint, and the call site names nothing.
 */
final class InjectedFactory
{
    public function __construct(
        private readonly ShopService $shop,
    ) {
    }

    public function build(): string
    {
        return $this->shop->run();
    }
}
