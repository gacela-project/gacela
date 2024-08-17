<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleC;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class DepProvC extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
