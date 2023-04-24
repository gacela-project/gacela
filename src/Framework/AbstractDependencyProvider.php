<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Container\Container;

abstract class AbstractDependencyProvider
{
    use ConfigResolverAwareTrait;

    abstract public function provideModuleDependencies(Container $container): void;
}
