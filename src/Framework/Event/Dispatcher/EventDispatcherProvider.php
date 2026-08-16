<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

/**
 * Holds the currently active event dispatcher so the {@see EventDispatchingCapabilities}
 * mixin can dispatch without depending on Config. Config pushes a lazy resolver at
 * bootstrap; every dispatch site reads through here. Before bootstrap (no resolver),
 * a shared NullEventDispatcher keeps dispatch sites silent.
 */
final class EventDispatcherProvider
{
    private static ?EventDispatcherInterface $dispatcher = null;

    private static ?NullEventDispatcher $preBootstrapDispatcher = null;

    /** @var (callable(): EventDispatcherInterface)|null */
    private static $resolver;

    /**
     * @param callable(): EventDispatcherInterface $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
        // A new setup brings its own listeners; drop the dispatcher built from the previous one.
        self::$dispatcher = null;
    }

    /**
     * Drops the memoized dispatcher, keeping the resolver that built it.
     *
     * `Gacela::bootstrap()` dispatches `GacelaBootstrapStartedEvent` before
     * `Config::init()`, so the guard on that dispatch memoizes the dispatcher
     * the setup held *before* `gacela.php` was merged in. When the merge then
     * installs a different one, every later dispatch still went to the old
     * object: listeners the merged setup registered never fired.
     *
     * The memo itself stays -- `shouldDispatch` guards sit on bench-gated hot
     * paths, so resolving per dispatch is not an option.
     */
    public static function refresh(): void
    {
        self::$dispatcher = null;
    }

    public static function get(): EventDispatcherInterface
    {
        if (self::$dispatcher instanceof EventDispatcherInterface) {
            return self::$dispatcher;
        }

        // Dispatch sites can run before bootstrap (e.g. clearing cache files);
        // without a resolver there is nothing to listen, so stay silent.
        if (self::$resolver === null) {
            return self::$preBootstrapDispatcher ??= new NullEventDispatcher();
        }

        return self::$dispatcher = (self::$resolver)();
    }

    public static function reset(): void
    {
        self::$dispatcher = null;
        self::$resolver = null;
    }
}
