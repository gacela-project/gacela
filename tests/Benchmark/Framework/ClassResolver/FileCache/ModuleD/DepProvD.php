<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleD;

use Gacela\Container\Container;
use Gacela\Framework\AbstractDependencyProvider;

final class DepProvD extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
