<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleA\Facade as ThirdPartyModuleAFacade;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleB\Facade as ThirdPartyModuleBFacade;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleC\Facade as ThirdPartyModuleCFacade;
use PHPUnit\Framework\TestCase;

/**
 * ProjectNamespaces is a list of namespaces sort by prio to resolve the Facade, Factory, Config or AbstractProvider.
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

    /**
     * The module-prefixed spelling, `{projectNamespace}\ModuleC\ModuleCFactory`,
     * which is what `docs/getting-a-dependency.md` shows for per-entrypoint
     * wiring. Its siblings above override with the bare `Factory` name, so this
     * is the one that keeps the documented shape honest.
     */
    public function test_override_factory_named_with_the_module_prefix(): void
    {
        $facade = new ThirdPartyModuleCFacade();

        self::assertSame('Overridden, from src\Main\ModuleC::StringC1', $facade->stringValueC1());
    }

    /**
     * The override extends the vendor Factory and replaces one method, so
     * everything it does not mention still resolves from the base -- the reason
     * a variant is a small subclass rather than a copy of the module.
     */
    public function test_a_method_the_override_does_not_replace_still_comes_from_the_base(): void
    {
        $facade = new ThirdPartyModuleCFacade();

        self::assertSame('Hi, from vendor\ThirdParty\ModuleC::StringC2', $facade->stringValueC2());
    }

    public function test_override_factory_from_second_highest_prio_namespace(): void
    {
        $facade = new ThirdPartyModuleBFacade();

        self::assertSame('Overridden, from src\CompanyB\ModuleB', $facade->stringValueB1());
    }
}
