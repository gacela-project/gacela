<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleC;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
        $container->set('provided-dependency', 'dependency-value');
    }
}
