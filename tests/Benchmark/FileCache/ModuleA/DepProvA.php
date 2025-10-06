<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleA;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class DepProvA extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
