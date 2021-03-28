<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile\RemoveKeyFromContainer;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_NAME = 'FACADE_NAME';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::FACADE_NAME, fn () => null);
        $container->remove(self::FACADE_NAME);
    }
}
