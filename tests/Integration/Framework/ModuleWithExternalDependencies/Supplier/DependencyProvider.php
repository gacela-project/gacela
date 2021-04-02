<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Supplier;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Dependent;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_DEPENDENT = 'FACADE_DEPENDENT';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCalculator($container);
    }

    private function addFacadeCalculator(Container $container): void
    {
        $container->set(self::FACADE_DEPENDENT, function (Container $container): Dependent\FacadeInterface {
            return $container->getLocator()->get(Dependent\Facade::class);
        });
    }
}
