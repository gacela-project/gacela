<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\Event\Dispatcher\ApplicationEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatcherProvider;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use Gacela\Framework\Exception\PluginStackException;
use Gacela\Framework\Plugins\Membership\ListenerMember;
use Gacela\Framework\Plugins\PluginStack;
use LogicException;
use Throwable;

use function array_map;
use function is_callable;
use function sprintf;

/**
 * @template TConfig of AbstractConfig = AbstractConfig
 */
abstract class AbstractFactory
{
    /** @use ConfigResolverAwareTrait<TConfig> */
    use ConfigResolverAwareTrait;
    use EventDispatchingCapabilities;

    /** @var array<string,Container> */
    private static array $containers = [];

    /**
     * The app-wide configuration, resolved once and shared by every module.
     *
     * Each module container used to be built by `Container::withConfig()`, which
     * walks the whole of `gacela.php` -- every binding, factory, alias,
     * contextual binding, tag and hook -- and does it again per Factory class.
     * Only the module's own Provider differs. That is 79 walks in this
     * repository alone, and the cost grows with the application's wiring rather
     * than with the number of modules.
     *
     * A module now gets a *scope* of this instead: registration is not copied,
     * a miss falls through, and anything the module registers shadows the parent
     * for that module alone -- which is what keeps two providers using the same
     * un-namespaced key from colliding.
     */
    private static ?Container $appContainer = null;

    /**
     * Modules that resolved no Provider at all. Their container is still usable
     * for make(), which autowires by type, but getProvidedDependency() has
     * nothing to read from and reports the missing Provider instead.
     *
     * @var array<string,bool>
     */
    private static array $providerless = [];

    /** @var array<string,mixed> */
    private array $instances = [];

    /**
     * Adopt the container bootstrap already built from this configuration,
     * instead of walking `gacela.php` a second time to reach the same result.
     *
     * Pushed in by {@see Gacela::bootstrap()} rather than pulled: a Factory
     * reaching for `Gacela::container()` would make the framework's entry point
     * a dependency of every module's factory, which is a cycle.
     *
     * @internal
     */
    public static function useAppContainer(Container $container): void
    {
        self::$appContainer = $container;
    }

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::resetModuleContainers();
        self::$appContainer = null;
    }

    /**
     * Drops each module's container, and the services its Provider set there,
     * while the application container `gacela.php` configured is kept.
     *
     * @internal see {@see \Gacela\Framework\Gacela::resetRequestState()}
     */
    public static function resetModuleContainers(): void
    {
        self::$containers = [];
        self::$providerless = [];
    }

    /**
     * @template T
     *
     * @param callable():T $creator
     *
     * @return T
     */
    protected function singleton(string $key, callable $creator): mixed
    {
        /** @var T $instance */
        $instance = $this->instances[$key] ??= $creator();

        return $instance;
    }

    /**
     * The declared implementations of one interface, in order, typed.
     *
     * Deliberately not `getProvidedDependency(InvoiceDecorator::class)`: that
     * call means "the thing registered under this id", and both analysers type
     * it as the class the id names. A stack registered under its contract
     * resolves to a `PluginStack` of that contract instead, so routing it
     * through the same call would make one id mean two things. It gets its own
     * accessor and `getProvidedDependency()` keeps meaning exactly one.
     *
     * @template TPlugin of object
     *
     * @param class-string<TPlugin> $contract
     *
     * @return PluginStack<TPlugin>
     */
    protected function getPluginStack(string $contract): PluginStack
    {
        // Read straight off the container rather than through
        // getProvidedDependency(): a stack is declared app-wide in gacela.php,
        // so a module that only *consumes* an extension point has no reason to
        // own a Provider, and being told it needs one would be a coupling the
        // declaration never asked for.
        $stack = $this->getContainer()->get($contract);

        if (!$stack instanceof PluginStack) {
            throw PluginStackException::notDeclared($contract, $this->attributePluginsOf($contract));
        }

        /** @var PluginStack<TPlugin> $stack */
        return $stack;
    }

    protected function getProvidedDependency(string $key): mixed
    {
        $container = $this->getContainer();

        if (self::$providerless[static::class]) {
            throw new ProviderNotFoundException(static::class);
        }

        return $container->get($key);
    }

    /**
     * Resolve a class through the module container with autowiring, so its
     * constructor dependencies and the container DI attributes (#[Inject],
     * #[Singleton], #[Factory], #[Lazy]) are honored — letting a create*() method
     * resolve a domain object by type instead of hand-wiring it.
     *
     * Pass $params to override constructor arguments by name (top level only);
     * the instance is then always built fresh.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<string, mixed> $params
     *
     * @return T
     */
    protected function make(string $className, array $params = []): object
    {
        return $this->getContainer()->make($className, $params);
    }

    /**
     * Read only once the stack is known to be missing, so a declared stack never
     * pays for it. A scan that fails here must not replace the message it was
     * asked to improve.
     *
     * @return list<string>
     */
    private function attributePluginsOf(string $contract): array
    {
        try {
            $plugins = Container::attributeMembers()->plugins;
        } catch (Throwable) {
            return [];
        }

        $classes = [];
        foreach ($plugins as $member) {
            if ($member->contract === $contract) {
                $classes[] = $member->plugin;
            }
        }

        return $classes;
    }

    private function getContainer(): Container
    {
        $containerKey = static::class;

        if (!isset(self::$containers[$containerKey])) {
            self::$containers[$containerKey] = $this->createContainerWithProvidedDependencies();
        }

        return self::$containers[$containerKey];
    }

    private function createContainerWithProvidedDependencies(): Container
    {
        $container = $this->appContainer()->createScope();
        $this->provideEventDispatcher($container);
        $this->scheduleAppWideExtensions($container);

        $resolver = (new ProviderResolver())->resolve($this);

        if ($resolver !== null) {
            // Between resolving the Provider and running it: the extension has
            // to be queued before the id is stored, because that store is what
            // drains the queue. Scheduling it earlier is impossible -- which
            // Provider this module has is not known until it is resolved.
            $this->scheduleExtensionsFor($resolver::class, $container);
        }

        $resolver?->register($container);

        if ($resolver !== null) {
            $this->notifyProviderRegistered($resolver::class);
        }

        // A module without a Provider still gets a container: make() autowires by
        // type and needs no bindings. Only getProvidedDependency() has nothing to
        // read from, and it reports the missing Provider itself.
        self::$providerless[static::class] = $resolver === null;

        return $container;
    }

    /**
     * The event dispatcher, in the one place a module reads its dependencies
     * from.
     *
     * This is what makes a project's own events ordinary: a Factory writes
     * `getProvidedDependency(EventDispatcherInterface::class)` and dispatches
     * through the same object the framework does -- no trait, no static
     * provider, and no second event system beside Gacela's.
     *
     * On the module scope rather than on the application container, because the
     * scope is where *provided dependencies* live. The application container is
     * what `debug:container` and `validate:config` report, and it should
     * describe the application: a framework service registered there would
     * appear in every project's binding report, and would stop `debug:container`
     * from ever saying "Container is empty" -- which is a diagnostic, and would
     * be answering about Gacela rather than about the reader's wiring.
     *
     * Skipped when anything already provides the id, so an application that
     * binds its own dispatcher app-wide keeps it; registered before the module's
     * Provider runs, so a Provider registering one for its own module wins too.
     * Resolved through {@see EventDispatcherProvider} on every `get()` rather
     * than captured here, so a scope built early never hands out a dispatcher
     * the setup has since replaced.
     */
    private function provideEventDispatcher(Container $scope): void
    {
        if ($scope->provides(EventDispatcherInterface::class)) {
            return;
        }

        $scope->set(
            EventDispatcherInterface::class,
            $scope->factory(fn (): EventDispatcherInterface => $this->withAttributeListeners(EventDispatcherProvider::get())),
        );
    }

    /**
     * Unless `disableEventListeners()` turned listening off. The class is
     * resolved on each event the way `Gacela::getRequired()` resolves it, from
     * the same application container, so a module double replaces it and
     * `Gacela::resetRequestState()` drops it.
     */
    private function withAttributeListeners(EventDispatcherInterface $dispatcher): EventDispatcherInterface
    {
        $appContainer = $this->appContainer();

        $setup = Config::getInstance()->getSetupGacela();
        if ($setup instanceof SetupGacela && !$setup->areEventListenersEnabled()) {
            return $dispatcher;
        }

        return new ApplicationEventDispatcher($dispatcher, static fn (): array => array_map(
            static fn (ListenerMember $member): array => [
                $member->event,
                static function (object $event) use ($member, $appContainer): void {
                    $listener = [Locator::getRequiredSingleton($member->class, $appContainer), $member->method];
                    if (!is_callable($listener)) {
                        throw new LogicException(sprintf('%s::%s() is listed as an #[AsListener] and is no public method: after renaming one, run `bin/gacela cache:clear`', $member->class, $member->method));
                    }

                    $listener($event);
                },
            ],
            Container::attributeListeners(),
        ));
    }

    /**
     * `extendService()` is app-wide configuration, but the service it decorates
     * is often registered by a *module's* Provider -- into that module's scope,
     * where an extension held by the parent can never reach it.
     *
     * So each scope schedules the extensions itself, skipping any id the parent
     * already owns: the parent applies those, and asking a scope to extend an
     * inherited instance is refused upstream rather than silently ignored.
     */
    private function scheduleAppWideExtensions(Container $scope): void
    {
        $parent = $this->appContainer();

        foreach (Config::getInstance()->getSetupGacela()->getServicesToExtend() as $id => $extensions) {
            if ($parent->provides($id)) {
                continue;
            }

            foreach ($extensions as $extension) {
                $scope->extend($id, $extension);
            }
        }
    }

    /**
     * The extensions aimed at *this* module's Provider.
     *
     * `extendService()` wraps an id wherever it is registered, which is right
     * when the id names one app-wide concept and wrong when two Providers reuse
     * an un-namespaced key on purpose. Naming the Provider is how a project
     * says *that* module's binding, and how one module decorates a sibling's
     * without shadowing the sibling's whole Provider class to change one line.
     *
     * @param class-string $providerClass
     */
    private function scheduleExtensionsFor(string $providerClass, Container $scope): void
    {
        $byId = Config::getInstance()->getSetupGacela()->getProviderServicesToExtend()[$providerClass] ?? [];

        foreach ($byId as $id => $extensions) {
            foreach ($extensions as $extension) {
                $scope->extend($id, $extension);
            }
        }
    }

    /**
     * Built on first use rather than at bootstrap: a process that never touches
     * a Factory -- a console command reading config, say -- should not pay to
     * assemble the container every module would have shared.
     */
    private function appContainer(): Container
    {
        if (self::$appContainer instanceof Container) {
            return self::$appContainer;
        }

        // Only reached when nothing bootstrapped: `Gacela::bootstrap()` hands
        // its container down through useAppContainer(), so a bootstrapped
        // application walks `gacela.php` once and modules become scopes of what
        // it already built.
        return self::$appContainer = Container::withConfig(Config::getInstance());
    }

    private function notifyProviderRegistered(string $providerClass): void
    {
        if (self::shouldDispatch(ProviderRegisteredEvent::class)) {
            self::dispatchEvent(new ProviderRegisteredEvent(
                $providerClass,
                ClassInfo::from($this)->getModuleName(),
            ));
        }
    }
}
