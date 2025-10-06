<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleA;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class ProModuleA extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
