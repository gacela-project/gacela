<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\AllAppModules\Domain\Module1;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Module1Provider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
