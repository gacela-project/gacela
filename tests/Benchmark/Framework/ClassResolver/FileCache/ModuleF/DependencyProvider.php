<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleF;

use Gacela\Container\Container;
use Gacela\Framework\AbstractDependencyProvider;

final class DependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
