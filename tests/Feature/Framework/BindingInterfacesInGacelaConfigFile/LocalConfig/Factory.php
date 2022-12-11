<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\Service;

final class Factory extends AbstractFactory
{
    public function __construct(
        private AbstractClass $resolvedClass,
        private AbstractFromAnonymousClass $resolvingAbstractAnonClassFunction,
        private AbstractFromCallable $resolvingAbstractAnonClassCallable,
        private InterfaceFromAnonymousClass $resolvingAnonClassFunction,
        private InterfaceFromCallable $resolvingAnonClassCallable,
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
