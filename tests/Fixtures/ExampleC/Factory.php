<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\FacadeInterface as ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\FacadeInterface as ExampleBFacade;
use GacelaTest\Fixtures\ExampleC\Service\ServiceC;

/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    public function createGreeter(): ServiceC
    {
        return new ServiceC(
            $this->getConfig()->getNumber(),
            $this->getExampleAFacade(),
            $this->getExampleBFacade()
        );
    }

    private function getExampleAFacade(): ExampleAFacade
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_A);
    }

    private function getExampleBFacade(): ExampleBFacade
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_B);
    }
}
