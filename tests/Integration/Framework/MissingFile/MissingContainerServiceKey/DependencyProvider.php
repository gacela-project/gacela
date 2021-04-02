<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\MissingFile\MissingContainerServiceKey;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class DependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
