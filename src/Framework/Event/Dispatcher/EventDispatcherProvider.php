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
    private static $resolver = null;

    /**
     * @param callable(): EventDispatcherInterface $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
        // A new setup brings its own listeners; drop the dispatcher built from the previous one.
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
