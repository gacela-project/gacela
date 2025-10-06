<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\AbstractFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\AbstractFromCallable;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\InterfaceFromAnonymousClass;
use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\InterfaceFromCallable;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    private Module\Facade $facade;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->facade = new Module\Facade();
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
            $this->facade->generateResolvedClass(),
        );
    }

    public function test_mapping_abstract_from_anonymous_class(): void
    {
        self::assertSame(
            AbstractFromAnonymousClass::class,
            $this->facade->generateResolveAbstractFromAnonymousClass(),
        );
    }

    public function test_mapping_abstract_from_callable(): void
    {
        self::assertSame(
            AbstractFromCallable::class,
            $this->facade->generateResolveAbstractFromCallable(),
        );
    }

    public function test_mapping_interface_from_anonymous_class(): void
    {
        self::assertSame(
            InterfaceFromAnonymousClass::class,
            $this->facade->generateResolveInterfaceFromAnonymousClass(),
        );
    }

    public function test_mapping_interface_from_callable(): void
    {
        self::assertSame(
            InterfaceFromCallable::class,
            $this->facade->generateResolveInterfaceFromCallable(),
        );
    }
}
