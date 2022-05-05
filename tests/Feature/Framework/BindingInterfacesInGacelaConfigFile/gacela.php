<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Infrastructure\ConcreteClass;

return static function (GacelaConfig $config): void {
    $config->mapInterface(AbstractClass::class, new ConcreteClass(true, 'string', 1, 1.2, ['array']));

    // Resolve anonymous-classes/callables from abstract classes and interfaces
    $config->mapInterface(
        AbstractFromAnonymousClass::class,
        new class() extends AbstractFromAnonymousClass {
            public function getClassName(): string
            {
                return AbstractFromAnonymousClass::class;
            }
        }
    );

    $config->mapInterface(
        AbstractFromCallable::class,
        new class() extends AbstractFromCallable {
            public function getClassName(): string
            {
                return AbstractFromCallable::class;
            }
        }
    );

    $config->mapInterface(
        InterfaceFromAnonymousClass::class,
        new class() implements InterfaceFromAnonymousClass {
            public function getClassName(): string
            {
                return InterfaceFromAnonymousClass::class;
            }
        }
    );

    $config->mapInterface(
        InterfaceFromCallable::class,
        new class() implements InterfaceFromCallable {
            public function getClassName(): string
            {
                return InterfaceFromCallable::class;
            }
        }
    );
    // Is it also possible to bind classes like => AbstractClass::class => SpecificClass::class
    // Check the test _BindingInterfacesWithInnerDependencies_ BUT be aware this way is not possible
    // if the class has dependencies that cannot be resolved automatically!
};
