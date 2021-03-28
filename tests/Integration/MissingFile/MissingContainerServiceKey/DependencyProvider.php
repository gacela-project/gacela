<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile\MissingContainerServiceKey;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;

final class DependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
