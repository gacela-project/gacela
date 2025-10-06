<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleD;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class DepProvD extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
