<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier;

use Gacela\Container\Container;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Locator;
use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent;

final class DependencyProvider extends AbstractDependencyProvider
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
            static fn () => Locator::getInstance()->get(Dependent\Facade::class),
        );
    }
}
