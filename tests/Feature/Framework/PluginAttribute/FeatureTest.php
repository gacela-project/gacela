<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginAttribute;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use Gacela\Framework\Plugins\Membership\PluginMember;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Aliased;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Bundle;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Central;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\CheckoutFacade;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Coupon;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Discount;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Loyalty;
use PHPUnit\Framework\TestCase;

use function array_map;
use function is_file;
use function unlink;

/**
 * A class joins a plugin stack by carrying `#[Plugin]`, and the module that
 * iterates the stack sees it after the members `gacela.php` declares.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_attribute_members_follow_the_declared_ones_by_priority_then_name(): void
    {
        $this->bootstrap([Central::class]);

        self::assertSame(['central', 'loyalty', 'aliased', 'bundle', 'coupon'], (new CheckoutFacade())->discountNames());
    }

    public function test_a_stack_declared_empty_is_filled_by_attributes_alone(): void
    {
        $this->bootstrap([]);

        self::assertSame(['loyalty', 'aliased', 'bundle', 'central', 'coupon'], (new CheckoutFacade())->discountNames());
    }

    public function test_a_warmed_cache_is_read_instead_of_scanning(): void
    {
        $this->bootstrap([Central::class], fileCache: true);
        $cache = MembershipCache::forScan(Config::getInstance()->getCacheDir(), Config::getInstance()->getAppRootDir(), Config::getInstance()->getSetupGacela()->getAppModulePaths(), Config::getInstance()->getSetupGacela()->getProjectNamespaces());
        $cache->write(new Members([new PluginMember(Discount::class, Coupon::class, 0), new PluginMember(Discount::class, Bundle::class, 0)]));

        try {
            self::assertSame(['central', 'coupon', 'bundle'], (new CheckoutFacade())->discountNames());
        } finally {
            unlink($cache->path());
        }
    }

    /**
     * With file caching off, as in development, a new or renamed `#[Plugin]`
     * class is seen on the next request, whatever an earlier `cache:warm` left.
     */
    public function test_with_file_caching_off_a_cache_file_is_not_read(): void
    {
        $this->bootstrap([Central::class]);
        $cache = MembershipCache::forScan(Config::getInstance()->getCacheDir(), Config::getInstance()->getAppRootDir(), Config::getInstance()->getSetupGacela()->getAppModulePaths(), Config::getInstance()->getSetupGacela()->getProjectNamespaces());
        $cache->write(new Members([new PluginMember(Discount::class, Coupon::class, 0)]));

        try {
            self::assertSame(['central', 'loyalty', 'aliased', 'bundle', 'coupon'], (new CheckoutFacade())->discountNames());
        } finally {
            unlink($cache->path());
        }
    }

    public function test_with_file_caching_on_the_first_scan_is_stored(): void
    {
        $this->bootstrap([Central::class], fileCache: true);
        $cache = MembershipCache::forScan(Config::getInstance()->getCacheDir(), Config::getInstance()->getAppRootDir(), Config::getInstance()->getSetupGacela()->getAppModulePaths(), Config::getInstance()->getSetupGacela()->getProjectNamespaces());

        try {
            self::assertFileDoesNotExist($cache->path());
            (new CheckoutFacade())->discountNames();
            self::assertSame(
                [Loyalty::class, Aliased::class, Bundle::class, Central::class, Coupon::class],
                array_map(static fn (PluginMember $member): string => $member->plugin, $cache->read()?->plugins ?? []),
            );
        } finally {
            if (is_file($cache->path())) {
                unlink($cache->path());
            }
        }
    }

    public function test_with_file_caching_off_nothing_is_written(): void
    {
        $this->bootstrap([Central::class]);
        (new CheckoutFacade())->discountNames();

        self::assertFileDoesNotExist(MembershipCache::forScan(Config::getInstance()->getCacheDir(), Config::getInstance()->getAppRootDir(), Config::getInstance()->getSetupGacela()->getAppModulePaths(), Config::getInstance()->getSetupGacela()->getProjectNamespaces())->path());
    }

    /**
     * @param list<class-string<Discount>> $declared
     */
    private function bootstrap(array $declared, bool $fileCache = false): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($declared, $fileCache): void {
            $config->resetInMemoryCache();
            $config->setFileCache($fileCache);
            $config->setProjectNamespaces([__NAMESPACE__ . '\Checkout']);
            $config->addPluginStack(Discount::class, $declared);
        });
    }
}
