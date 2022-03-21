<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class DepProvB extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
