<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA\Facade as VendorModuleAFacade;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleB\Facade as VendorModuleBFacade;
use PHPUnit\Framework\TestCase;

/**
 * ProjectNamespaces is a list of namespaces sort by prio to resolve the Facade, Factory, Config or DependencyProvider.
 *
 * In this example, we are using the Facade from a vendor's module (`vendor\Persona\ModuleA\Facade`), and that Facade
 * is using its Factory. However, we wanted to override that Factory to extend its functionality, when resolving the
 * Factory for that module, Gacela will find the overridden Factory from our ModuleA, and it will use this custom class
 * instead of the Factory from vendor.
 */
final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/default.php');

            $config->setProjectNamespaces([
                'GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\CompanyA',
                'GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\CompanyB',
            ]);
        });
    }

    public function test_override_factory_from_highest_prio_namespace(): void
    {
        $facade = new VendorModuleAFacade();

        self::assertSame('Overridden, from src\CompanyA\ModuleA::StringA', $facade->sayHiA());
    }

    public function test_non_overridden_factory_method_from_vendor(): void
    {
        $facade = new VendorModuleAFacade();

        self::assertSame('Hi, from vendor\Persona\ModuleA::StringB', $facade->sayHiB());
    }

    public function test_override_factory_from_second_highest_prio_namespace(): void
    {
        $facade = new VendorModuleBFacade();

        self::assertSame('Overridden, from src\CompanyB\ModuleB', $facade->sayHi());
    }
}
