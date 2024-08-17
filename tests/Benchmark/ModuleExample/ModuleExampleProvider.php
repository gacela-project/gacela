<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ModuleExample;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class ModuleExampleProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('key', 'value');
    }
}
