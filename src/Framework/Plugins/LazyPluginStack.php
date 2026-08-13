<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins;

use ArrayIterator;
use Gacela\Container\ContainerInterface;
use Gacela\Framework\Exception\PluginStackException;
use Traversable;

use function array_key_exists;
use function class_exists;
use function count;

/**
 * Default {@see PluginStack}: resolves each entry through the container on
 * first access and keeps the instance.
 *
 * @template TPlugin of object
 *
 * @implements PluginStack<TPlugin>
 */
final class LazyPluginStack implements PluginStack
{
    /**
     * A second cache, for the reason {@see LazyHandlerRegistry} documents: the
     * container caches by binding style, so an entry bound as a factory would
     * be rebuilt on every pass. A stack's contract is that iterating it twice
     * yields the same plugins, and that has to hold however they are bound.
     *
     * @var array<int, TPlugin>
     */
    private array $resolved = [];

    /**
     * @param class-string<TPlugin> $contract
     * @param list<class-string<TPlugin>> $plugins
     */
    public function __construct(
        private readonly string $contract,
        private readonly array $plugins,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @return list<TPlugin>
     */
    public function all(): array
    {
        $all = [];

        foreach (array_keys($this->plugins) as $position) {
            $all[] = $this->at($position);
        }

        return $all;
    }

    /**
     * @return Traversable<int, TPlugin>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->plugins);
    }

    public function isEmpty(): bool
    {
        return $this->plugins === [];
    }

    /**
     * @return TPlugin
     */
    private function at(int $position): object
    {
        if (array_key_exists($position, $this->resolved)) {
            return $this->resolved[$position];
        }

        $className = $this->plugins[$position];
        // Before the container, which answers `null` for a name that resolves
        // to nothing: the contract check below would then report a missing
        // class as one that "does not implement" the contract.
        if (!class_exists($className)) {
            throw PluginStackException::classDoesNotExist($className, $this->contract);
        }

        /** @var object $instance */
        $instance = $this->container->get($className);

        // Checked on first resolve rather than at registration, because a class
        // name in `gacela.php` is a string until something loads it. One check
        // per entry per process, and it names the class and the contract --
        // otherwise a misregistration surfaces as a TypeError inside whatever
        // the consumer does with the plugin, nowhere near the line that
        // registered it.
        if (!$instance instanceof $this->contract) {
            throw PluginStackException::doesNotImplementContract($className, $this->contract);
        }

        /** @var TPlugin $instance */
        return $this->resolved[$position] = $instance;
    }
}
