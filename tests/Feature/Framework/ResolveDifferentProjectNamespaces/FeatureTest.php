<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA\Facade as VendorPersonaFacade;
use PHPUnit\Framework\TestCase;

/**
 * ProjectNamespaces is a list of namespaces on which gacela will look to resolve the Facade, Factory, Config or DependencyProvider.
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
                'GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces',
            ]);
        });
    }

    public function test_override_factory_from_other_namespace(): void
    {
        $facade = new VendorPersonaFacade();

        self::assertSame('Overridden string from ModuleA', $facade->sayHi());
    }
}
