<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService;

/**
 * A nullsafe call crosses the boundary exactly as much as a plain one.
 */
final class NullsafeCallFactory
{
    public function __construct(
        private readonly ?ShopService $shop = null,
    ) {
    }

    public function build(): ?string
    {
        return $this->shop?->run();
    }
}
