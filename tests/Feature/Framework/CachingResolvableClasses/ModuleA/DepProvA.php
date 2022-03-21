<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class DepProvA extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
