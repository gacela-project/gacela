<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules\TestModule1;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class TestModule1Provider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
