<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendService\Module;

use ArrayObject;
use Gacela\Container\Container;
use Gacela\Framework\AbstractDependencyProvider;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const ARRAY_AS_OBJECT = 'ARRAY_AS_OBJECT';

    public const ARRAY_FROM_FUNCTION = 'ARRAY_FROM_FUNCTION';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::ARRAY_AS_OBJECT, new ArrayObject([1, 2]));

        $container->set(self::ARRAY_FROM_FUNCTION, static fn () => new ArrayObject([1, 2]));
    }
}
