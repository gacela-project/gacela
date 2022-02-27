<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\LocalConfig;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class DepProvCustom extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
