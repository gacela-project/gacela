<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use RuntimeException;

final class ThrowingProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        throw new RuntimeException('this provider needs a database');
    }
}
