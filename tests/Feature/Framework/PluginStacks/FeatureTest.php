<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacks;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Exception\PluginStackException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\CheckoutFacade;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\Discount;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\FiveHundredOff;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\TenPercentOff;
use PHPUnit\Framework\TestCase;

/**
 * An extension point declared in `gacela.php` and iterated by a module that
 * does not know who filled it.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_module_iterates_the_stack_it_did_not_fill(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addPluginStack(Discount::class, [TenPercentOff::class]);
        });

        self::assertSame(900, (new CheckoutFacade())->priceOf(1000));
    }

    /**
     * Order is declaration order, and it is observable: ten percent off a
     * thousand then five hundred off is 400; the other way round it is 450.
     */
    public function test_the_plugins_run_in_the_order_they_were_declared(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addPluginStack(Discount::class, [TenPercentOff::class, FiveHundredOff::class]);
        });

        self::assertSame(400, (new CheckoutFacade())->priceOf(1000));
    }

    /**
     * The reason there is no second verb for contributing: calling the same one
     * again appends, seed first, so a later config source adds to a stack it
     * did not declare.
     */
    public function test_a_second_declaration_appends_rather_than_replaces(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addPluginStack(Discount::class, [TenPercentOff::class]);
            $config->addPluginStack(Discount::class, [FiveHundredOff::class]);
        });

        self::assertSame(400, (new CheckoutFacade())->priceOf(1000));
    }

    public function test_an_empty_stack_leaves_the_value_alone(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addPluginStack(Discount::class, []);
        });

        self::assertSame(1000, (new CheckoutFacade())->priceOf(1000));
    }

    /**
     * Asking for a stack nobody declared is a mistake worth naming, not a
     * silently empty collection that makes the extension point look broken.
     */
    public function test_asking_for_an_undeclared_stack_says_how_to_declare_it(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
        });

        $this->expectException(PluginStackException::class);
        // What is wrong, then how to fix it, in that order.
        $this->expectExceptionMessageMatches('/No plugin stack is declared for .*Discount.*addPluginStack/s');

        (new CheckoutFacade())->priceOf(1000);
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
