<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesResolver;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Infrastructure\ConcreteClass;

return static fn () => new class () extends AbstractConfigGacela {
    public function mappingInterfaces(
        MappingInterfacesResolver $interfacesResolver,
        array $globalServices = []
    ): void {
        // Resolve an abstract class from a concrete instance of a class
        $interfacesResolver->bind(AbstractClass::class, new ConcreteClass(true, 'string', 1, 1.2, ['array']));

        // Resolve anonymous-classes/callables from abstract classes and interfaces
        $interfacesResolver->bind(AbstractFromAnonymousClass::class, $this->usingAbstractFromAnonymousClass());
        $interfacesResolver->bind(AbstractFromCallable::class, $this->usingAbstractFromCallable());
        $interfacesResolver->bind(InterfaceFromAnonymousClass::class, $this->usingInterfaceFromAnonymousClass());
        $interfacesResolver->bind(InterfaceFromCallable::class, $this->usingInterfaceFromCallable());
        // Is it also possible to bind classes like => AbstractClass::class => SpecificClass::class
        // Check the test _BindingInterfacesWithDependenciesAndGlobalServices_ BUT
        // be aware this way is not possible if the class has dependencies that cannot be resolved automatically!
    }

    private function usingAbstractFromAnonymousClass(): AbstractFromAnonymousClass
    {
        return new class () extends AbstractFromAnonymousClass {
            public function getClassName(): string
            {
                return AbstractFromAnonymousClass::class;
            }
        };
    }

    private function usingAbstractFromCallable(): callable
    {
        return static fn () => new class () extends AbstractFromCallable {
            public function getClassName(): string
            {
                return AbstractFromCallable::class;
            }
        };
    }

    private function usingInterfaceFromAnonymousClass(): InterfaceFromAnonymousClass
    {
        return new class () implements InterfaceFromAnonymousClass {
            public function getClassName(): string
            {
                return InterfaceFromAnonymousClass::class;
            }
        };
    }

    private function usingInterfaceFromCallable(): callable
    {
        return static fn () => new class () implements InterfaceFromCallable {
            public function getClassName(): string
            {
                return InterfaceFromCallable::class;
            }
        };
    }
};
