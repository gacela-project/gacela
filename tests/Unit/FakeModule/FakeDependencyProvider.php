<?php

declare(strict_types=1);

namespace GacelaTest\Unit\FakeModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class FakeAbstractProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
