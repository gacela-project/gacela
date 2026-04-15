<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Attribute\ProvidesScanner;
use Gacela\Framework\Container\Container;

/**
 * @template TConfig of AbstractConfig = AbstractConfig
 */
abstract class AbstractProvider
{
    /** @use ConfigResolverAwareTrait<TConfig> */
    use ConfigResolverAwareTrait;

    public function provideModuleDependencies(Container $container): void
    {
    }

    /**
     * @internal
     */
    public function register(Container $container): void
    {
        ProvidesScanner::scan($this, $container);
        $this->provideModuleDependencies($container);
    }
}
