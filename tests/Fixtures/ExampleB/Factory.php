<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleB;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleA;
use GacelaTest\Fixtures\ExampleB\Service\ServiceB;

final class Factory extends AbstractFactory
{
    public function createGreeter(): ServiceB
    {
        return new ServiceB(
            $this->getExampleAFacade()
        );
    }

    private function getExampleAFacade(): ExampleA\FacadeInterface
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_A);
    }
}
