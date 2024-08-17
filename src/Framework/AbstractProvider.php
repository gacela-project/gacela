<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Container\Container;

abstract class AbstractProvider
{
    use ConfigResolverAwareTrait;

    abstract public function provideModuleDependencies(Container $container): void;
}
