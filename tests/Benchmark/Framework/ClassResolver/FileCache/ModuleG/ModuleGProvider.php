<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleG;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class ModuleGProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
