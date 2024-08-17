<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\AbstractClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\InterfaceFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\Service;

final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly AbstractClass $resolvedClass,
        private readonly AbstractFromAnonymousClass $resolvingAbstractAnonClassFunction,
        private readonly AbstractFromCallable $resolvingAbstractAnonClassCallable,
        private readonly InterfaceFromAnonymousClass $resolvingAnonClassFunction,
        private readonly InterfaceFromCallable $resolvingAnonClassCallable,
    ) {
    }

    public function createCompanyService(): Service
    {
        return new Service(
            $this->resolvedClass,
            $this->resolvingAbstractAnonClassFunction,
            $this->resolvingAbstractAnonClassCallable,
            $this->resolvingAnonClassFunction,
            $this->resolvingAnonClassCallable,
        );
    }
}
