<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain\Module1;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class Module1DependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
