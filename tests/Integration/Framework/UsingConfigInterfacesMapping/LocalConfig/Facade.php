<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function generateCompanyAndName(): string
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateCompanyAndName();
    }

    public function generateResolvedClass(): array
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateResolvedClass();
    }

    public function generateTypesAnonClassCallable(): array
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateTypesAnonClassCallable();
    }

    public function generateTypesAnonClassFunction(): array
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateTypesAnonClassFunction();
    }

    public function generateAbstractTypesAnonClassCallable(): array
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateTypesAbstractAnonClassCallable();
    }

    public function generateAbstractTypesAnonClassFunction(): array
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateTypesAbstractAnonClassFunction();
    }
}
