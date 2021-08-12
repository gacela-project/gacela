<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile;

use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromAnonymousClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractFromCallable;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\InterfaceFromCallable;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    private LocalConfig\Facade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        $this->facade = new LocalConfig\Facade();
    }

    public function test_resolved_class(): void
    {
        self::assertSame(
            [
                'bool' => true,
                'string' => 'string',
                'int' => 1,
                'float' => 1.2,
                'array' => ['array'],
            ],
            $this->facade->generateResolvedClass()
        );
    }

    public function test_mapping_abstract_from_anonymous_class(): void
    {
        self::assertSame(
            AbstractFromAnonymousClass::class,
            $this->facade->generateResolveAbstractFromAnonymousClass()
        );
    }

    public function test_mapping_abstract_from_callable(): void
    {
        self::assertSame(
            AbstractFromCallable::class,
            $this->facade->generateResolveAbstractFromCallable()
        );
    }

    public function test_mapping_interface_from_anonymous_class(): void
    {
        self::assertSame(
            InterfaceFromAnonymousClass::class,
            $this->facade->generateResolveInterfaceFromAnonymousClass()
        );
    }

    public function test_mapping_interface_from_callable(): void
    {
        self::assertSame(
            InterfaceFromCallable::class,
            $this->facade->generateResolveInterfaceFromCallable()
        );
    }
}
