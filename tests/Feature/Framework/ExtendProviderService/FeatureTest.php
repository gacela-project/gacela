<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderService;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ExtendProviderService\Catalog\CatalogFacade;
use GacelaTest\Feature\Framework\ExtendProviderService\Catalog\CatalogProvider;
use GacelaTest\Feature\Framework\ExtendProviderService\Checkout\CheckoutFacade;
use PHPUnit\Framework\TestCase;

/**
 * Two modules registering the same un-namespaced id, which module scopes make
 * legal on purpose. An extension aimed at one Provider must reach that one and
 * leave the other alone.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_only_the_named_providers_service_is_wrapped(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->extendProviderService(
                CatalogProvider::class,
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'wrapped'],
            );
        });

        self::assertSame('catalog-wrapped', (new CatalogFacade())->label());
        self::assertSame('checkout', (new CheckoutFacade())->label());
    }

    /**
     * The contrast that makes the pair worth having: the same wrap declared
     * app-wide reaches both modules, because the id names it in both.
     */
    public function test_an_app_wide_extension_reaches_every_module_using_the_id(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->extendService(
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'wrapped'],
            );
        });

        self::assertSame('catalog-wrapped', (new CatalogFacade())->label());
        self::assertSame('checkout-wrapped', (new CheckoutFacade())->label());
    }

    public function test_the_extension_receives_the_modules_container(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->extendProviderService(
                CatalogProvider::class,
                CatalogProvider::LABEL,
                static fn (array $labels, Container $container): array => [...$labels, $container instanceof Container ? 'scoped' : 'none'],
            );
        });

        self::assertSame('catalog-scoped', (new CatalogFacade())->label());
    }

    public function test_extensions_stack_in_declaration_order(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->extendProviderService(
                CatalogProvider::class,
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'one'],
            );
            $config->extendProviderService(
                CatalogProvider::class,
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'two'],
            );
        });

        self::assertSame('catalog-one-two', (new CatalogFacade())->label());
    }

    /**
     * An extension aimed at a Provider this application does not have is inert
     * rather than fatal: a package may ship one for a module the project has
     * not installed.
     */
    public function test_an_extension_for_an_absent_provider_changes_nothing(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->extendProviderService(
                'App\Nowhere\NowhereProvider',
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'wrapped'],
            );
        });

        self::assertSame('catalog', (new CatalogFacade())->label());
    }

    private function bootstrap(callable $configFn): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $configFn($config);
        });
    }
}
