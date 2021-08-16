<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\Service;

final class Factory extends AbstractFactory
{
    private AbstractClass $resolvedClass;
    private AbstractFromAnonymousClass $resolvingAbstractAnonClassFunction;
    private AbstractFromCallable $resolvingAbstractAnonClassCallable;
    private InterfaceFromAnonymousClass $resolvingAnonClassFunction;
    private InterfaceFromCallable $resolvingAnonClassCallable;

    public function __construct(
        AbstractClass $resolvedClass,
        AbstractFromAnonymousClass $resolvingAbstractAnonClassFunction,
        AbstractFromCallable $resolvingAbstractAnonClassCallable,
        InterfaceFromAnonymousClass $resolvingAnonClassFunction,
        InterfaceFromCallable $resolvingAnonClassCallable
    ) {
        $this->resolvedClass = $resolvedClass;
        $this->resolvingAbstractAnonClassFunction = $resolvingAbstractAnonClassFunction;
        $this->resolvingAbstractAnonClassCallable = $resolvingAbstractAnonClassCallable;
        $this->resolvingAnonClassFunction = $resolvingAnonClassFunction;
        $this->resolvingAnonClassCallable = $resolvingAnonClassCallable;
    }

    public function createCompanyService(): Service
    {
        return new Service(
            $this->resolvedClass,
            $this->resolvingAbstractAnonClassFunction,
            $this->resolvingAbstractAnonClassCallable,
            $this->resolvingAnonClassFunction,
            $this->resolvingAnonClassCallable
        );
    }
}
