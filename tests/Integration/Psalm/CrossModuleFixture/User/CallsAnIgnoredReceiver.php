<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ShopEnvironmentInterface;

final class CallsAnIgnoredReceiver
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
