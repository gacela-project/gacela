<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent;

final class Provider extends AbstractProvider
{
    public const FACADE_DEPENDENT = 'FACADE_DEPENDENT';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCalculator($container);
    }

    private function addFacadeCalculator(Container $container): void
    {
        $container->set(
            self::FACADE_DEPENDENT,
            static fn (Container $container) => $container->getLocator()->get(Dependent\Facade::class),
        );
    }
}
