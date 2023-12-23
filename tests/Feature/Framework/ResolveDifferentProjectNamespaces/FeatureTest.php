<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleA\Facade as ThirdPartyModuleAFacade;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleB\Facade as ThirdPartyModuleBFacade;
use PHPUnit\Framework\TestCase;

/**
 * ProjectNamespaces is a list of namespaces sort by prio to resolve the Facade, Factory, Config or DependencyProvider.
 *
 * In this example, we are using the Facade from a third-party vendor's module (`vendor\ThirdParty\ModuleA\Facade`),
 * and when that Facade uses its Factory, gacela will resolve it from our `src\Main` namespace, because we have the same
 * module structure as that ThirdParty, and we have defined the `src\Main` as first thing in the GacelaConfig::setProjectNamespaces().
 */
final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);

            $config->setProjectNamespaces([
                'GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Main',
                'GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Secondary',
            ]);
        });
    }

    public function test_override_factory_from_highest_prio_namespace(): void
    {
        $facade = new ThirdPartyModuleAFacade();

        self::assertSame('Overridden, from src\CompanyA\ModuleA::StringA', $facade->stringValueA1());
    }

    public function test_non_overridden_factory_method_from_vendor(): void
    {
        $facade = new ThirdPartyModuleAFacade();

        self::assertSame('Hi, from vendor\ThirdParty\ModuleA::StringA2', $facade->stringValueA2());
    }

    public function test_override_factory_from_second_highest_prio_namespace(): void
    {
        $facade = new ThirdPartyModuleBFacade();

        self::assertSame('Overridden, from src\CompanyB\ModuleB', $facade->stringValueB1());
    }
}
