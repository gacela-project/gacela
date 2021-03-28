<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\ExampleAFacadeInterface;
use GacelaTest\Fixtures\ExampleB\ExampleBFacadeInterface;
use GacelaTest\Fixtures\ExampleC\Service\ServiceC;

/**
 * @method ExampleCRepository getRepository()
 * @method ExampleCConfig getConfig()
 */
final class ExampleCFactory extends AbstractFactory
{
    public function createGreeter(): ServiceC
    {
        return new ServiceC(
            $this->getConfig()->getNumber(),
            $this->getExampleAFacade(),
            $this->getExampleBFacade(),
            $this->getRepository()
        );
    }

    private function getExampleAFacade(): ExampleAFacadeInterface
    {
        return $this->getProvidedDependency(ExampleCDependencyProvider::FACADE_A);
    }

    private function getExampleBFacade(): ExampleBFacadeInterface
    {
        return $this->getProvidedDependency(ExampleCDependencyProvider::FACADE_B);
    }
}
