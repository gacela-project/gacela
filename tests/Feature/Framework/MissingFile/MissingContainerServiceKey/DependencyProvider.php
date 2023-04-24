<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingContainerServiceKey;

use Gacela\Container\Container;
use Gacela\Framework\AbstractDependencyProvider;

final class DependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
