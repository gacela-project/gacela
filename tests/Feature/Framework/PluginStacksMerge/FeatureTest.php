<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacksMerge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\CheckoutFacade;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\Discount;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\FiveHundredOff;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\TenPercentOff;
use PHPUnit\Framework\TestCase;

/**
 * Two config sources filling one stack: `gacela.php` contributes
 * FiveHundredOff, the bootstrap closure contributes TenPercentOff. Neither
 * replaces the other -- which is the whole reason there is no separate
 * "contribute" verb.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_both_config_sources_fill_the_same_stack(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addPluginStack(Discount::class, [TenPercentOff::class]);
        });

        // -10% of 1000 = 900, then -500 = 400. The bootstrap closure's setup
        // is the base and `gacela.php` merges onto it, so the closure's entry
        // runs first -- the order is worth pinning, because "appends" is only
        // a useful promise if which end it appends to is fixed.
        self::assertSame(400, (new CheckoutFacade())->priceOf(1000));
    }

    /**
     * A class both sources declare runs once, at the position the first source
     * gave it — otherwise re-stating the seed in an override would silently
     * apply the same decorator twice.
     */
    public function test_a_plugin_both_sources_declare_runs_once(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addPluginStack(Discount::class, [TenPercentOff::class, FiveHundredOff::class]);
        });

        // gacela.php declares FiveHundredOff too. Applied twice this would be
        // 1000 -> 900 -> 400 -> -100 clamped to 0.
        self::assertSame(400, (new CheckoutFacade())->priceOf(1000));
    }
}
