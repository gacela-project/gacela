<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopEnvironmentInterface;

final class EnvironmentCallFactory
{
    public function __construct(
        private readonly ShopEnvironmentInterface $environment,
    ) {
    }

    public function build(): string
    {
        return $this->environment->name();
    }
}
