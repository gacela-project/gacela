<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent;
use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier\Service\HelloName;

final class Factory extends AbstractFactory
{
    public function createGreeter(): HelloName
    {
        return new HelloName(
            $this->getDependentFacade()
        );
    }

    private function getDependentFacade(): Dependent\FacadeInterface
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_DEPENDENT);
    }
}
