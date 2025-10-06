<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingContainerServiceKey;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
