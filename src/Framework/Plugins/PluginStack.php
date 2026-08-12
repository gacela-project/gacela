<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins;

use Countable;
use IteratorAggregate;

/**
 * Every implementation of one interface, in declaration order, typed.
 *
 * The third of three ways to hold a collection, and the one that carries a
 * contract. Which to reach for:
 *
 * - a {@see HandlerRegistry} answers *the one implementation for this key*
 *   and throws on a key it does not know;
 * - a tag (`GacelaConfig::tag()`) answers *all of these*, untyped, contributed
 *   from anywhere;
 * - a stack answers *all implementations of this interface*, in order, and
 *   refuses anything that is not one.
 *
 * The contract is the whole point: without it the answer is a tag. It is what
 * types the `foreach` in the consumer, and what turns a misregistration into a
 * failure that names the class instead of a `TypeError` deep inside somebody
 * else's loop.
 *
 * Declared in `gacela.php` with `addPluginStack()`, frozen after boot, and
 * resolved through the container so each entry is autowired.
 *
 * @template TPlugin of object
 *
 * @extends IteratorAggregate<int, TPlugin>
 */
interface PluginStack extends IteratorAggregate, Countable
{
    /**
     * @return list<TPlugin>
     */
    public function all(): array;

    public function isEmpty(): bool;
}
