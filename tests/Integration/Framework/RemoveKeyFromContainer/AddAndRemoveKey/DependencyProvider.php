<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RemoveKeyFromContainer\AddAndRemoveKey;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_NAME = 'FACADE_NAME';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::FACADE_NAME, fn () => null);
        $container->remove(self::FACADE_NAME);
    }
}
