<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ProjectEvents\Ordering;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Empty, and still needed: `getProvidedDependency()` refuses to answer for a
 * module that resolved no Provider at all, and the dispatcher is reached
 * through the parent container rather than registered here.
 */
final class OrderingProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
