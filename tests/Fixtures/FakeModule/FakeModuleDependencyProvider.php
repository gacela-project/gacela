<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\FakeModule;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class FakeModuleDependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        // TODO: Implement provideModuleDependencies() method.
    }
}
