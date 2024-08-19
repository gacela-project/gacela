<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module2;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    /** @var array<class-string,class-string> */
    public array $bindings = [
        Module2FacadeInterface::class => Facade::class,
    ];

    public function provideModuleDependencies(Container $container): void
    {
    }
}
