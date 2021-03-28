<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA\FacadeInterface as ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\FacadeInterface as ExampleBFacade;
use GacelaTest\Fixtures\ExampleC\Domain\Service\ServiceC;
use GacelaTest\Fixtures\ExampleC\Infrastructure\Config;
use GacelaTest\Fixtures\ExampleC\Infrastructure\Persistence\Repository;

/**
 * @method Repository getRepository()
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
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

    private function getExampleAFacade(): ExampleAFacade
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_A);
    }

    private function getExampleBFacade(): ExampleBFacade
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_B);
    }
}
