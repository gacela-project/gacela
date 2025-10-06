<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleG;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class ModuleGProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
