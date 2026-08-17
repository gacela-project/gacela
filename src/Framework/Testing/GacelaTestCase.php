<?php

declare(strict_types=1);

namespace Gacela\Framework\Testing;

use Closure;
use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function class_exists;
use function dirname;
use function implode;
use function interface_exists;
use function is_object;
use function is_string;
use function is_subclass_of;
use function sprintf;

/**
 * Base class for tests that bootstrap a Gacela application.
 *
 * It removes the reset boilerplate every Gacela test needs: each
 * bootstrap starts from a clean in-memory state, and tearDown() drops all
 * Gacela singletons so state never leaks into the next test.
 *
 * Every bootstrap also records the framework lifecycle events, which
 * powers the container assertions:
 *
 * ```php
 * final class CheckoutTest extends GacelaTestCase
 * {
 *     public function test_facade_resolves_payment_gateway(): void
 *     {
 *         $this->bootstrapGacelaWithConfig(__DIR__, ['retries' => 3]);
 *
 *         (new CheckoutFacade())->pay();
 *
 *         $this->assertServiceResolved(PaymentGateway::class);
 *     }
 * }
 * ```
 */
abstract class GacelaTestCase extends TestCase
{
    use ContainerFixture;

    /** @var list<GacelaEventInterface> */
    private array $recordedGacelaEvents = [];

    protected function tearDown(): void
    {
        $this->resetContainer();
        $this->recordedGacelaEvents = [];
    }

    /**
     * Bootstrap Gacela from a clean in-memory state, recording all
     * framework lifecycle events dispatched from this bootstrap onwards.
     *
     * @param null|Closure(GacelaConfig):void $configFn
     */
    protected function bootstrapGacela(string $appRootDir, ?Closure $configFn = null): void
    {
        $this->recordedGacelaEvents = [];

        Gacela::bootstrap($appRootDir, function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $config->registerGenericListener(function (GacelaEventInterface $event): void {
                $this->recordedGacelaEvents[] = $event;
            });

            if ($configFn instanceof Closure) {
                $configFn($config);
            }
        });
    }

    /**
     * Bootstrap one module with its neighbours replaced -- the everyday test of
     * a modular application.
     *
     * ```php
     * $this->bootstrapModule(__DIR__, InvoiceFacade::class, doubles: [
     *     BillingFacade::class => $this->createStub(BillingFacade::class),
     *     PaymentGatewayInterface::class => new FakeGateway(),
     * ]);
     * ```
     *
     * Two things happen. Module discovery is narrowed to the module the Facade
     * names, so `doctor`, `list:modules` and the boundary assertions answer
     * about that module rather than about the whole application -- the slice.
     * And each double is applied through the primitive that fits it, so a
     * caller does not have to know which of three seams a given dependency
     * arrives on:
     *
     * - an `AbstractFactory`, `AbstractConfig` or `AbstractProvider` instance
     *   replaces that pillar of the module its key's Facade names, exactly as
     *   {@see ContainerFixture::swapModuleFactory()} does -- and with the same
     *   guard, so a key that is not a Facade fails rather than registering a
     *   double nothing reads;
     * - anything else keyed by a **class or interface** answers wherever that
     *   type is asked for: a pillar constructor argument, a `Container::get()`,
     *   and -- for an object -- the resolver's global instances, which is the
     *   path a neighbour Facade reached through `getProvidedDependency()` or
     *   `#[ServiceMap]` travels;
     * - anything keyed by a **container id** replaces that id wherever it is
     *   registered, including in the module's own Provider, which nothing
     *   written at application level can otherwise reach.
     *
     * A neighbour worth doubling has to leave its Facade non-`final`: a
     * consumer that type-hints a `final` class cannot be handed a stand-in for
     * it by anyone. Where that is not an option, replace the neighbour's
     * Factory instead -- which is the pillar double above, and needs nothing
     * from the class it replaces.
     *
     * **What this cannot check.** That the module actually depends on what is
     * being doubled. From the framework side, reflection sees a module's
     * `#[Provides]` ids and return types, its `#[ServiceMap]` accessors and its
     * pillar constructors -- and that misses a plugin-stack interface named
     * only as a call argument, an app-wide binding autowired into a nested
     * constructor, and anything a Provider registers from inside a method body.
     * On the reference application that is four of nine legitimate doubles, so
     * a "this module does not depend on X" check would refuse real tests. What
     * *is* checked is precise: an object registered under a class or interface
     * it is not an instance of would reach a consumer that type-hints the real
     * one, and that fails here instead of at the call site.
     *
     * Bootstraps once, like {@see bootstrapGacela()}; `tearDown()` drops it.
     *
     * @param class-string                                $facadeClass the module under test
     * @param array<string, object|Closure|class-string> $doubles     class, interface or container id => its replacement
     * @param null|Closure(GacelaConfig):void             $configFn    composed with the narrowing, not instead of it
     */
    protected function bootstrapModule(
        string $appRootDir,
        string $facadeClass,
        array $doubles = [],
        ?Closure $configFn = null,
    ): void {
        $moduleDir = $this->moduleDirectoryOf($facadeClass, $appRootDir);

        foreach ($doubles as $id => $double) {
            $this->assertDoubleIsUsableAs($id, $double);
        }

        $this->bootstrapGacela($appRootDir, static function (GacelaConfig $config) use ($doubles, $configFn): void {
            foreach ($doubles as $id => $double) {
                self::registerDoubleIn($config, $id, $double);
            }

            if ($configFn instanceof Closure) {
                $configFn($config);
            }
        });

        $this->narrowModulePathsTo($moduleDir);
        $this->applyResolvedClassDoubles($doubles);
    }

    /**
     * Bootstrap Gacela with the given config key-values, the most common
     * override needed in tests.
     *
     * @param array<string,mixed> $configKeyValues
     */
    protected function bootstrapGacelaWithConfig(string $appRootDir, array $configKeyValues): void
    {
        $this->bootstrapGacela($appRootDir, static function (GacelaConfig $config) use ($configKeyValues): void {
            $config->addAppConfigKeyValues($configKeyValues);
        });
    }

    /**
     * All framework lifecycle events recorded since the last bootstrap.
     *
     * @return list<GacelaEventInterface>
     */
    protected function recordedGacelaEvents(): array
    {
        return $this->recordedGacelaEvents;
    }

    /**
     * The recorded events of one type, in dispatch order.
     *
     * @template T of GacelaEventInterface
     *
     * @param class-string<T> $eventClass
     *
     * @return list<T>
     */
    protected function recordedGacelaEventsOf(string $eventClass): array
    {
        /** @var list<T> */
        return array_values(array_filter(
            $this->recordedGacelaEvents,
            static fn (GacelaEventInterface $event): bool => $event instanceof $eventClass,
        ));
    }

    /**
     * Assert that an event of the given class was dispatched since the last
     * bootstrap.
     *
     * Works for the events the application dispatches as readily as for the
     * framework's own: `bootstrapGacela()` registers a generic listener, and a
     * project event goes through the same dispatcher.
     *
     * ```php
     * $this->bootstrapGacela(__DIR__);
     *
     * (new BillingFacade())->issueInvoice('acme', 10_000);
     *
     * $this->assertEventDispatched(InvoiceIssuedEvent::class);
     * ```
     *
     * Matched by inheritance, the way `registerSpecificListener()` matches, so
     * naming a parent class or an interface asserts about the whole family
     * below it. To assert on a payload, read the events instead:
     * {@see recordedGacelaEventsOf()} returns them typed and in dispatch order.
     *
     * @param class-string<GacelaEventInterface> $eventClass
     */
    protected function assertEventDispatched(string $eventClass): void
    {
        $this->assertGacelaEventsAreBeingRecorded();

        self::assertNotSame([], $this->recordedGacelaEventsOf($eventClass), sprintf(
            'No "%s" was dispatched. Dispatched: %s.',
            $eventClass,
            $this->dispatchedClassList(),
        ));
    }

    /**
     * Assert that the container instantiated the given service id since
     * the last bootstrap.
     */
    protected function assertServiceResolved(string $serviceId): void
    {
        $this->assertGacelaEventsAreBeingRecorded();

        self::assertContains(
            $serviceId,
            array_map(
                static fn (ServiceResolvedEvent $event): string => $event->id(),
                $this->recordedGacelaEventsOf(ServiceResolvedEvent::class),
            ),
            sprintf('Service "%s" was not resolved by the container.', $serviceId),
        );
    }

    /**
     * Assert that a binding/alias/contextual binding was registered under
     * the given id since the last bootstrap.
     */
    protected function assertBindingRegistered(string $id): void
    {
        $this->assertGacelaEventsAreBeingRecorded();

        self::assertContains(
            $id,
            array_map(
                static fn (BindingRegisteredEvent $event): string => $event->id(),
                $this->recordedGacelaEventsOf(BindingRegisteredEvent::class),
            ),
            sprintf('Binding "%s" was not registered in the container.', $id),
        );
    }

    /**
     * The directory the module under test lives in -- the slice.
     *
     * Derived from the Facade the same way the pillar swaps derive a module, so
     * naming anything else fails with the message that already explains why the
     * Facade is the name a module answers to.
     *
     * @param class-string $facadeClass
     */
    private function moduleDirectoryOf(string $facadeClass, string $appRootDir): string
    {
        if (!class_exists($facadeClass) || !is_subclass_of($facadeClass, AbstractFacade::class)) {
            throw ModuleDoubleException::notAFacade($facadeClass);
        }

        $file = (new ReflectionClass($facadeClass))->getFileName();

        // A Facade is a userland class and always has a file; reflection cannot
        // say so, and narrowing to a path derived from `false` would point
        // discovery at the working directory. Not narrowing at all is the
        // honest fallback.
        return is_string($file) ? dirname($file) : $appRootDir;
    }

    /**
     * Point module discovery at the module under test, and nowhere else.
     *
     * After the bootstrap rather than inside it, because `gacela.php` merges
     * *onto* the closure: an application that declares its own module paths --
     * which is the only kind that has more than one module to narrow away --
     * would overwrite a narrowing written in the closure, and the slice would
     * silently be the whole application.
     */
    private function narrowModulePathsTo(string $moduleDir): void
    {
        $setup = Config::getInstance()->getSetupGacela();

        if ($setup instanceof SetupGacela) {
            $setup->setAppModulePaths([$moduleDir]);
        }
    }

    /**
     * The one thing about a double that can be checked without guessing.
     *
     * A double registered under a class or an interface is handed to whoever
     * asked for that type. One that is not an instance of it gets all the way
     * to the call site before failing, with a TypeError naming neither the test
     * nor the id.
     */
    private function assertDoubleIsUsableAs(string $id, mixed $double): void
    {
        // A pillar double is checked where it is applied: the swap derives the
        // module from the Facade and refuses anything else, with this same
        // exception, so a second check here would only move the throw earlier.
        if (self::isPillarDouble($double) || !is_object($double) || $double instanceof Closure) {
            return;
        }

        if ((class_exists($id) || interface_exists($id)) && !$double instanceof $id) {
            throw ModuleDoubleException::notAnInstanceOf($id, $double);
        }
    }

    /**
     * The three pillar swaps, behind the one guard they share.
     *
     * A pillar double is registered under the key its module's own class would
     * have taken, and the resolver derives that key from the Facade. Anything
     * else names no module, so the double would be registered where nothing
     * ever reads it -- which is what the guard refuses, and what narrows the
     * key to the type the swaps are declared for.
     *
     * @param AbstractConfig|AbstractFactory|AbstractProvider $double
     */
    private function swapModulePillarDouble(string $id, object $double): void
    {
        $this->assertNamesAFacade($id);

        if ($double instanceof AbstractFactory) {
            $this->swapModuleFactory($id, $double);
        } elseif ($double instanceof AbstractConfig) {
            $this->swapModuleConfig($id, $double);
        } else {
            $this->swapModuleProvider($id, $double);
        }
    }

    /**
     * @phpstan-assert class-string<AbstractFacade<AbstractFactory>> $className
     *
     * @psalm-assert class-string<AbstractFacade<AbstractFactory>> $className
     */
    private function assertNamesAFacade(string $className): void
    {
        if (!class_exists($className) || !is_subclass_of($className, AbstractFacade::class)) {
            throw ModuleDoubleException::notAFacade($className);
        }
    }

    /**
     * Everything a double needs from the configuration, which is everything
     * except the pillar swaps and the resolved-class overrides: both of those
     * are dropped by the reset that a bootstrap begins with.
     *
     * Which registration depends on what the key is, because the two answers an
     * application gives are reached differently and the registrations do not
     * compose -- a lazy service registered here satisfies a pending extension
     * at application level, and the module's Provider then overwrites the
     * result, so asking for both gets neither.
     *
     * A **class or interface** is answered by a binding and a lazy service.
     * The binding is what the class resolver reads when it builds a pillar's
     * constructor arguments -- it consults bindings and nothing else. The lazy
     * service is what `Container::get()` consults first, and it is the only one
     * of the two that survives `gacela.php`: the application file merges *onto*
     * the bootstrap closure, so a binding written here loses to one the file
     * declares for the same class.
     *
     * A **container id** is answered by an extension, because a Provider
     * registers its own ids on the module's container after the application's
     * services -- so nothing written at application level wins one, and
     * `extendService()` is the single reach a bootstrap has into a module's own
     * container. An id nothing registers anywhere is left alone: there is
     * nothing in the module for the double to stand in for.
     */
    private static function registerDoubleIn(GacelaConfig $config, string $id, mixed $double): void
    {
        if (self::isPillarDouble($double)) {
            return;
        }

        $factory = self::doubleFactory($double);

        if (!class_exists($id) && !interface_exists($id)) {
            // The extension is handed the service it replaces and the module's
            // own container, in that order.
            $config->extendService($id, static fn (mixed $current, Container $container): mixed => $factory($container));

            return;
        }

        /**
         * @var class-string $id
         * @var class-string|object $double
         */
        $config->addBinding($id, $double);
        $config->addLazy($id, $factory);
    }

    /**
     * The double as something the container can call for a value.
     *
     * A class-string is a recipe rather than a value, so it is resolved the way
     * the same string in `addBinding()` would be -- handing the string itself
     * back as the service is never what a caller meant.
     */
    private static function doubleFactory(mixed $double): Closure
    {
        if ($double instanceof Closure) {
            return $double;
        }

        if (is_object($double)) {
            return static fn (): object => $double;
        }

        /** @var class-string $double */
        return static fn (Container $container): mixed => $container->get($double);
    }

    /**
     * The doubles that only exist once Gacela has been bootstrapped: a bootstrap
     * resets the resolver's global instances, so an override registered before
     * one is an override that never happened.
     *
     * Nothing is re-reset afterwards. The bootstrap this runs behind has just
     * cleared the Facade and Factory memos, and the pillar swaps clear them
     * again themselves -- a third clear here would be a call no test could tell
     * from its absence.
     *
     * @param array<string, object|Closure|class-string> $doubles
     */
    private function applyResolvedClassDoubles(array $doubles): void
    {
        foreach ($doubles as $id => $double) {
            if (self::isPillarDouble($double)) {
                /** @var AbstractConfig|AbstractFactory|AbstractProvider $double */
                $this->swapModulePillarDouble($id, $double);
            } elseif (is_object($double) && !$double instanceof Closure) {
                // The path a neighbour Facade travels: the Locator consults the
                // resolver's global instances before the container, which is how
                // `getProvidedDependency()` and `#[ServiceMap]` both reach one.
                //
                // Registered whatever the key is. A key that names no class
                // takes a slot nothing ever looks up -- the Locator is only ever
                // asked for a class -- so testing for one would be a branch with
                // the same outcome on both sides.
                Gacela::overrideExistingResolvedClass($id, $double);
            }
        }
    }

    private static function isPillarDouble(mixed $double): bool
    {
        return $double instanceof AbstractFactory
            || $double instanceof AbstractConfig
            || $double instanceof AbstractProvider;
    }

    /**
     * That there is a recording to read at all.
     *
     * These assertions answer from events, and only {@see bootstrapGacela()}
     * registers the listener that collects them. A test that called
     * `Gacela::bootstrap()` directly -- which is what migrating an existing
     * test to this base class leaves behind -- records nothing, and every
     * assertion below then fails with "was not resolved" about a service that
     * resolved perfectly well while nothing was watching.
     *
     * Checked first so the failure names the cause rather than the symptom.
     * A bootstrap through `bootstrapGacela()` records its own start and finish
     * whatever else happens, so an empty recording can only mean the listener
     * was never registered.
     */
    private function assertGacelaEventsAreBeingRecorded(): void
    {
        $message = <<<'MESSAGE'
            No Gacela events were recorded, so this assertion cannot answer anything.
            Bootstrap with $this->bootstrapGacela(...) instead of Gacela::bootstrap(...):
            it registers the listener these assertions read.
            MESSAGE;

        self::assertNotSame([], $this->recordedGacelaEvents, $message);
    }

    /**
     * The distinct event classes recorded, in dispatch order.
     *
     * Named in the failure message because the useful answer to "my event was
     * not dispatched" is usually another event that was -- a listener wired to
     * the wrong class, or a flow that stopped earlier than the test assumed.
     * Distinct, because the resolver events alone would otherwise fill the
     * message hundreds of lines deep.
     */
    private function dispatchedClassList(): string
    {
        $classes = [];

        foreach ($this->recordedGacelaEvents as $event) {
            $classes[$event::class] = true;
        }

        return $classes === [] ? 'nothing' : implode(', ', array_keys($classes));
    }
}
