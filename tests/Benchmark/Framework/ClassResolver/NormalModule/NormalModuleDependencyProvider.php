<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class NormalModuleAbstractProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('key', 'value');
    }
}
