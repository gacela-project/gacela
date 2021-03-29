<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithExternalDependencies\Supplier;

use Gacela\AbstractFactory;
use GacelaTest\Integration\ModuleWithExternalDependencies\Dependent\Facade;
use GacelaTest\Integration\ModuleWithExternalDependencies\Supplier\Service\HelloName;

final class Factory extends AbstractFactory
{
    public function createGreeter(): HelloName
    {
        return new HelloName(
            $this->getExampleAFacade()
        );
    }

    private function getExampleAFacade(): Facade
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_DEPENDENT);
    }
}
