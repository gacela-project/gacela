<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleB;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\Service\ServiceB;

/**
 * @method ExampleBConfig getConfig()
 */
final class ExampleBFactory extends AbstractFactory
{
    public function createGreeter(): ServiceB
    {
        return new ServiceB(
            $this->getExampleAFacade()
        );
    }

    private function getExampleAFacade(): ExampleAFacade
    {
        return $this->getProvidedDependency(ExampleBDependencyProvider::FACADE_A);
    }
}
