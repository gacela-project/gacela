<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\Framework\Plugins\Membership\ListenerMember;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Checkout\CheckoutFacade;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Log;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Mailer\MailerFacade;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\OrderPlaced;
use LogicException;
use PHPUnit\Framework\TestCase;

use function unlink;

/**
 * A method listens to an application event by carrying `#[AsListener]`, and
 * runs after the listeners `gacela.php` registers.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Log::$lines = [];
        Gacela::resetCache();
    }

    public function test_attribute_listeners_run_after_the_registered_ones(): void
    {
        $this->bootstrap();

        (new CheckoutFacade())->placeOrder(7);

        self::assertSame(['central 7', 'mailer 7', 'audit 7'], Log::$lines);
    }

    public function test_an_attribute_listener_is_reported_by_the_guard(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setProjectNamespaces([__NAMESPACE__ . '\Shop']);
        });

        self::assertTrue((new CheckoutFacade())->isOrderPlacedListenedTo());
    }

    public function test_with_event_listeners_disabled_none_runs(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setProjectNamespaces([__NAMESPACE__ . '\Shop']);
            $config->disableEventListeners();
        });

        (new CheckoutFacade())->placeOrder(7);

        self::assertSame([], Log::$lines);
    }

    /**
     * The framework's own dispatch sites never see the attribute listeners,
     * which is what keeps a resolution guard as cheap as before.
     */
    public function test_the_framework_dispatcher_does_not_serve_them(): void
    {
        $this->bootstrap();

        Config::getEventDispatcher()->dispatch(new OrderPlaced(7));

        self::assertSame(['central 7'], Log::$lines);
    }

    public function test_a_warmed_cache_is_read_instead_of_scanning(): void
    {
        $this->bootstrap(fileCache: true);
        $config = Config::getInstance();
        $cache = MembershipCache::forScan($config->getCacheDir(), $config->getAppRootDir(), $config->getSetupGacela()->getAppModulePaths(), $config->getSetupGacela()->getProjectNamespaces());
        $cache->write(new Members([], [], [new ListenerMember(OrderPlaced::class, MailerFacade::class, 'onOrderPlaced')]));

        try {
            (new CheckoutFacade())->placeOrder(7);
            self::assertSame(['central 7', 'mailer 7'], Log::$lines);
        } finally {
            unlink($cache->path());
        }
    }

    public function test_a_cached_listener_renamed_since_names_the_fix(): void
    {
        $this->bootstrap(fileCache: true);
        $config = Config::getInstance();
        $cache = MembershipCache::forScan($config->getCacheDir(), $config->getAppRootDir(), $config->getSetupGacela()->getAppModulePaths(), $config->getSetupGacela()->getProjectNamespaces());
        $cache->write(new Members([], [], [new ListenerMember(OrderPlaced::class, MailerFacade::class, 'renamedSince')]));

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('MailerFacade::renamedSince() is listed as an #[AsListener] and is no public method');
            (new CheckoutFacade())->placeOrder(7);
        } finally {
            unlink($cache->path());
        }
    }

    private function bootstrap(bool $fileCache = false): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($fileCache): void {
            $config->resetInMemoryCache();
            $config->setFileCache($fileCache);
            $config->setProjectNamespaces([__NAMESPACE__ . '\Shop']);
            $config->registerSpecificListener(OrderPlaced::class, static function (OrderPlaced $event): void {
                Log::$lines[] = 'central ' . $event->id;
            });
        });
    }
}
