<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendService\Module;

use ArrayObject;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

/**
 * Testing Backward Compatibility for AbstractDependencyProvider.
 * Use AbstractProvider when AbstractDependencyProvider is completely removed.
 */
final class DependencyProvider extends AbstractDependencyProvider
{
    public const ARRAY_AS_OBJECT = 'ARRAY_AS_OBJECT';

    public const ARRAY_FROM_FUNCTION = 'ARRAY_FROM_FUNCTION';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::ARRAY_AS_OBJECT, new ArrayObject([1, 2]));

        $container->set(self::ARRAY_FROM_FUNCTION, static fn (): ArrayObject => new ArrayObject([1, 2]));
    }
}
