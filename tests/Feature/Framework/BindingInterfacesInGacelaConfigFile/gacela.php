<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Infrastructure\ConcreteClass;

// Is it also possible to bind classes like => AbstractClass::class => SpecificClass::class
// Check the test _BindingInterfacesWithInnerDependencies_ BUT be aware this way is not possible
// if the class has dependencies that cannot be resolved automatically!
return static fn (GacelaConfig $config) => $config
    ->addMappingInterface(
        AbstractClass::class,
        new ConcreteClass(true, 'string', 1, 1.2, ['array'])
    )
    // Resolve anonymous-classes/callables from abstract classes and interfaces
    ->addMappingInterface(
        AbstractFromAnonymousClass::class,
        new class() extends AbstractFromAnonymousClass {
            public function getClassName(): string
            {
                return AbstractFromAnonymousClass::class;
            }
        }
    )
    ->addMappingInterface(
        AbstractFromCallable::class,
        new class() extends AbstractFromCallable {
            public function getClassName(): string
            {
                return AbstractFromCallable::class;
            }
        }
    )
    ->addMappingInterface(
        InterfaceFromAnonymousClass::class,
        new class() implements InterfaceFromAnonymousClass {
            public function getClassName(): string
            {
                return InterfaceFromAnonymousClass::class;
            }
        }
    )
    ->addMappingInterface(
        InterfaceFromCallable::class,
        new class() implements InterfaceFromCallable {
            public function getClassName(): string
            {
                return InterfaceFromCallable::class;
            }
        }
    );
