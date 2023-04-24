<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule;

use Gacela\Container\Container;
use Gacela\Framework\AbstractDependencyProvider;

final class NormalModuleDependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('key', 'value');
    }
}
