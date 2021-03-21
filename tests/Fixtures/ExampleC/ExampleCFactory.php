<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\ExampleBFacade;
use GacelaTest\Fixtures\ExampleC\Service\ServiceC;

/**
 * @method ExampleCConfig getConfig()
 */
final class ExampleCFactory extends AbstractFactory
{
    public function createGreeter(): ServiceC
    {
        return new ServiceC(
            $this->getExampleAFacade(),
            $this->getExampleBFacade()
        );
    }

    private function getExampleAFacade(): ExampleAFacade
    {
        return $this->getProvidedDependency(ExampleCDependencyProvider::FACADE_A);
    }

    private function getExampleBFacade(): ExampleBFacade
    {
        return $this->getProvidedDependency(ExampleCDependencyProvider::FACADE_B);
    }
}
