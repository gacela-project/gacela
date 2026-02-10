<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Container\Container;

/**
 * Base class for module providers.
 *
 * @template TConfig of AbstractConfig
 */
abstract class AbstractProvider
{
    /** @use ConfigResolverAwareTrait<TConfig> */
    use ConfigResolverAwareTrait;

    /**
     * Provide dependencies to the module container.
     *
     * @param Container $container The dependency container
     */
    abstract public function provideModuleDependencies(Container $container): void;
}
