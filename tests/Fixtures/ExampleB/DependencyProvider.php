<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleB;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use GacelaTest\Fixtures\ExampleA\FacadeInterface as ExampleAFacade;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_A = 'FACADE_A';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCalculator($container);
    }

    private function addFacadeCalculator(Container $container): void
    {
        $container->set(self::FACADE_A, function (Container $container): ExampleAFacade {
            return $container->getLocator()->get(ExampleAFacade::class);
        });
    }
}
