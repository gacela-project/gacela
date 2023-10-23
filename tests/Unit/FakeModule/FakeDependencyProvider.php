<?php

declare(strict_types=1);

namespace GacelaTest\Unit\FakeModule;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class FakeDependencyProvider extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
