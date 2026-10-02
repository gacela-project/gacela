<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameInvalidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameValidCandidateFoundEvent;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\RequestState\Cart\Cart;
use GacelaTest\Integration\Framework\RequestState\Cart\CartFacade;
use GacelaTest\Integration\Framework\RequestState\Cart\Request;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;
use function in_array;

/**
 * Two requests served by one long-running process, the way FrankenPHP worker
 * mode, Laravel Octane or RoadRunner serve them.
 */
final class RequestStateResetTest extends TestCase
{
    /** @var list<GacelaEventInterface> */
    private array $events = [];

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setProjectNamespaces([__NAMESPACE__]);
            $config->registerGenericListener(function (GacelaEventInterface $event): void {
                $this->events[] = $event;
            });
        });
    }

    protected function tearDown(): void
    {
        Request::$user = '';
        Gacela::resetCache();
    }

    /**
     * Without this one, the test below would pass on a fixture that never
     * held anything across requests.
     */
    public function test_without_a_reset_the_second_request_sees_the_first(): void
    {
        $this->serveAs('alice', static fn () => self::shopAndReadTheUser(new CartFacade()));

        Request::$user = 'bob';

        self::assertSame(['book'], (new CartFacade())->items());
        self::assertSame('alice', (new CartFacade())->userName());
    }

    public function test_after_the_reset_the_second_request_sees_nothing_of_the_first(): void
    {
        $this->serveAs('alice', static fn () => self::shopAndReadTheUser(new CartFacade()));

        Gacela::resetRequestState();
        Request::$user = 'bob';

        self::assertSame([], (new CartFacade())->items());
        self::assertSame('bob', (new CartFacade())->userName());
    }

    public function test_what_gacela_get_handed_out_is_not_handed_out_again(): void
    {
        $this->serveAs('alice', static function (): void {
            /** @var CartFacade $facade */
            $facade = Gacela::get(CartFacade::class);
            self::shopAndReadTheUser($facade);
        });

        Gacela::resetRequestState();
        Request::$user = 'bob';

        /** @var CartFacade $facade */
        $facade = Gacela::get(CartFacade::class);
        self::assertSame([], $facade->items());
        self::assertSame('bob', $facade->userName());
    }

    /**
     * `Gacela::get()` keeps what it hands out; a stateful service fetched
     * through it would otherwise come back to the next request as it was left.
     */
    public function test_a_service_from_gacela_get_starts_fresh(): void
    {
        /** @var Cart $cart */
        $cart = Gacela::get(Cart::class);
        $cart->add('book');

        Gacela::resetRequestState();

        /** @var Cart $next */
        $next = Gacela::get(Cart::class);
        self::assertSame([], $next->items());
    }

    /**
     * What the next request does not pay for: finding the classes again, or
     * configuring the containers from `gacela.php` again.
     */
    public function test_the_second_request_stays_warm(): void
    {
        $this->serveAs('alice', static fn () => self::shopAndReadTheUser(new CartFacade()));
        $config = Config::getInstance();
        $appContainer = Gacela::container();
        $pillarContainer = AbstractClassResolver::pillarContainer();

        Gacela::resetRequestState();
        $this->events = [];
        Request::$user = 'bob';
        (new CartFacade())->items();
        (new CartFacade())->userName();

        self::assertSame($config, Config::getInstance());
        self::assertSame($appContainer, Gacela::container());
        self::assertSame($pillarContainer, AbstractClassResolver::pillarContainer());
        self::assertSame([], $this->eventsOf([
            ClassNameValidCandidateFoundEvent::class,
            ClassNameInvalidCandidateFoundEvent::class,
            ClassNameNotFoundEvent::class,
            BindingRegisteredEvent::class,
        ]));
    }

    /**
     * A request that leaves both kinds of state behind: a Factory singleton
     * holding the items, and a provided service holding the user.
     */
    private static function shopAndReadTheUser(CartFacade $facade): void
    {
        $facade->addItem('book');
        $facade->userName();
    }

    /**
     * @param callable():void $request
     */
    private function serveAs(string $user, callable $request): void
    {
        Request::$user = $user;
        $request();
    }

    /**
     * @param list<class-string> $classes
     *
     * @return list<GacelaEventInterface>
     */
    private function eventsOf(array $classes): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (GacelaEventInterface $event): bool => in_array($event::class, $classes, true),
        ));
    }
}
