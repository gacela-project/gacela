<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain;

interface ShopEnvironmentInterface
{
    public function name(): string;
}
