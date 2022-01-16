<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Infrastructure\ConcreteClass;

return static fn () => new class () extends AbstractConfigGacela {
    public function mappingInterfaces(array $globalServices): array
    {
        return [
            // Resolve an abstract class from a concrete instance of a class
            AbstractClass::class => new ConcreteClass(true, 'string', 1, 1.2, ['array']),

            // Resolve anonymous-classes/callables from abstract classes and interfaces
            AbstractFromAnonymousClass::class => $this->resolveAbstractFromAnonymousClass(),
            AbstractFromCallable::class => $this->resolveAbstractFromCallable(),
            InterfaceFromAnonymousClass::class => $this->resolveInterfaceFromAnonymousClass(),
            InterfaceFromCallable::class => $this->resolveInterfaceFromCallable(),

            // Is it also possible to bind classes like => AbstractClass::class => SpecificClass::class
            // Check the test _BindingInterfacesWithDependenciesAndGlobalServices_ BUT
            // be aware this way is not possible if the class has dependencies that cannot be resolved automatically!
        ];
    }

    private function resolveAbstractFromAnonymousClass(): AbstractFromAnonymousClass
    {
        return new class () extends AbstractFromAnonymousClass {
            public function getClassName(): string
            {
                return AbstractFromAnonymousClass::class;
            }
        };
    }

    private function resolveAbstractFromCallable(): callable
    {
        return static fn () => new class () extends AbstractFromCallable {
            public function getClassName(): string
            {
                return AbstractFromCallable::class;
            }
        };
    }

    private function resolveInterfaceFromAnonymousClass(): InterfaceFromAnonymousClass
    {
        return new class () implements InterfaceFromAnonymousClass {
            public function getClassName(): string
            {
                return InterfaceFromAnonymousClass::class;
            }
        };
    }

    private function resolveInterfaceFromCallable(): callable
    {
        return static fn () => new class () implements InterfaceFromCallable {
            public function getClassName(): string
            {
                return InterfaceFromCallable::class;
            }
        };
    }
};
