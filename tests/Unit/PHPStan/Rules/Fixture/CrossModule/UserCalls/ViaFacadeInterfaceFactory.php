<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\ShopFacadeInterface;

/**
 * Type-hinting the interface rather than the Facade is the same sanctioned
 * crossing, and is what `FacadeInterfaceInSyncRule` assumes consumers do.
 */
final class ViaFacadeInterfaceFactory
{
    public function __construct(
        private readonly ShopFacadeInterface $shop,
    ) {
    }

    public function build(): string
    {
        return $this->shop->browse();
    }
}
