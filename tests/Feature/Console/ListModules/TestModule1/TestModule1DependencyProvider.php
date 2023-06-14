<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules\TestModule1;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class TestModule1DependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
