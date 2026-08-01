<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\PlanCache;

/**
 * One constructor-plan cache for every container Gacela builds.
 *
 * Gacela's containers are sibling roots, not a tree: {@see \Gacela\Framework\AbstractFactory}
 * keeps one per Factory class, and {@see \Gacela\Framework\ClassResolver\AbstractClassResolver}
 * and {@see \Gacela\Framework\Gacela} each hold one more. Without a shared cache
 * every one of them reflects the classes they have in common -- and they have
 * plenty in common, because the bindings from `gacela.php` reach all of them.
 *
 * Only reflection output travels through it: constructor parameters,
 * instantiability, `#[Inject]` properties. Bindings, contextual bindings,
 * aliases, tags, singletons and stored instances stay private to each
 * container, so sharing this cannot make one module resolve like another.
 *
 * @internal
 */
final class SharedPlanCache
{
    private static ?PlanCache $instance = null;

    public static function getInstance(): PlanCache
    {
        return self::$instance ??= new PlanCache();
    }

    /**
     * Not a correctness crutch: a plan is keyed on a class name and a class's
     * shape cannot change within a process, so nothing here can go stale.
     * `Gacela::resetCache()` calls it to hold up its own contract -- reset means
     * reset -- and to hand the memory back in a process that resets for a
     * living: a worker re-bootstrapping between jobs, or a warm command.
     */
    public static function resetCache(): void
    {
        self::$instance = null;
    }
}
