<?php

declare(strict_types=1);

use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Setup\SetupGacela;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Infrastructure\ConcreteClass;

return static fn () => (new SetupGacela())->setMappingInterfaces(
    static function (MappingInterfacesBuilder $mappingInterfacesBuilder, array $externalServices): void {
        // Resolve an abstract class from a concrete instance of a class
        $mappingInterfacesBuilder->bind(AbstractClass::class, new ConcreteClass(true, 'string', 1, 1.2, ['array']));

        // Resolve anonymous-classes/callables from abstract classes and interfaces
        $mappingInterfacesBuilder->bind(
            AbstractFromAnonymousClass::class,
            new class() extends AbstractFromAnonymousClass {
                public function getClassName(): string
                {
                    return AbstractFromAnonymousClass::class;
                }
            }
        );

        $mappingInterfacesBuilder->bind(
            AbstractFromCallable::class,
            static fn () => new class() extends AbstractFromCallable {
                public function getClassName(): string
                {
                    return AbstractFromCallable::class;
                }
            }
        );

        $mappingInterfacesBuilder->bind(
            InterfaceFromAnonymousClass::class,
            new class() implements InterfaceFromAnonymousClass {
                public function getClassName(): string
                {
                    return InterfaceFromAnonymousClass::class;
                }
            }
        );

        $mappingInterfacesBuilder->bind(
            InterfaceFromCallable::class,
            static fn () => new class() implements InterfaceFromCallable {
                public function getClassName(): string
                {
                    return InterfaceFromCallable::class;
                }
            }
        );
        // Is it also possible to bind classes like => AbstractClass::class => SpecificClass::class
        // Check the test _BindingInterfacesWithInnerDependencies_ BUT be aware this way is not possible
        // if the class has dependencies that cannot be resolved automatically!
    }
);
