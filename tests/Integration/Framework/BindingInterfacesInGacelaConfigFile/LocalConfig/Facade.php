<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function generateResolvedClass(): array
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateResolvedClass();
    }

    public function generateResolveAbstractFromAnonymousClass(): string
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateResolveAbstractFromAnonymousClass();
    }

    public function generateResolveAbstractFromCallable(): string
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateResolveAbstractFromCallable();
    }

    public function generateResolveInterfaceFromAnonymousClass(): string
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateResolveInterfaceFromAnonymousClass();
    }

    public function generateResolveInterfaceFromCallable(): string
    {
        return $this->getFactory()
            ->createCompanyService()
            ->generateResolveInterfaceFromCallable();
    }
}
