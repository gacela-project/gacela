<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;

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
        self::$containers = [];
        self::$providerless = [];
        self::$appContainer = null;
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
        $this->scheduleAppWideExtensions($container);

        $resolver = (new ProviderResolver())->resolve($this);
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
