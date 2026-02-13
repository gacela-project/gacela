<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Container\Container;

/**
 * @template TConfig of AbstractConfig = AbstractConfig
 */
abstract class AbstractProvider
{
    /** @use ConfigResolverAwareTrait<TConfig> */
    use ConfigResolverAwareTrait;

    abstract public function provideModuleDependencies(Container $container): void;
}
