<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins;

use Gacela\Container\ContainerInterface;
use OutOfBoundsException;

use function array_key_exists;
use function array_keys;
use function implode;
use function sprintf;

/**
 * Default {@see HandlerRegistry} that resolves handlers through the container
 * on first access and caches the resulting instance.
 *
 * @template THandler of object
 * @template TKey of string|int
 *
 * @implements HandlerRegistry<THandler, TKey>
 */
final class LazyHandlerRegistry implements HandlerRegistry
{
    /** @var array<string|int, object> */
    private array $resolved = [];

    /**
     * @param array<TKey, class-string<THandler>> $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly ContainerInterface $container,
    ) {
    }

    public function get(string|int $key): object
    {
        if (array_key_exists($key, $this->resolved)) {
            /** @var THandler */
            return $this->resolved[$key];
        }

        if (!array_key_exists($key, $this->handlers)) {
            throw new OutOfBoundsException(sprintf(
                'No handler registered for key "%s". Known keys: %s',
                (string) $key,
                $this->handlers === [] ? '(none)' : implode(', ', array_map(
                    static fn (string|int $k): string => (string) $k,
                    array_keys($this->handlers),
                )),
            ));
        }

        /** @var THandler $instance */
        $instance = $this->container->get($this->handlers[$key]);
        $this->resolved[$key] = $instance;

        return $instance;
    }

    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->handlers);
    }

    /**
     * @return list<TKey>
     */
    public function keys(): array
    {
        /** @var list<TKey> $keys */
        $keys = array_keys($this->handlers);

        return $keys;
    }
}
