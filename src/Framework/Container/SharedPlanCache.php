<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\PlanCache;

/**
 * One constructor-plan cache for every container Gacela builds.
 *
 * Module containers no longer need it: {@see \Gacela\Framework\AbstractFactory}
 * takes a scope of the app container per Factory class, and a scope inherits its
 * parent's plan registry. What this still covers is the roots that are unrelated
 * to that tree and to each other -- {@see \Gacela\Framework\ClassResolver\AbstractClassResolver},
 * {@see Locator}, {@see \Gacela\Framework\Bootstrap\Setup\GacelaConfigExtender}
 * and {@see \Gacela\Framework\Gacela}'s own. Without it each would reflect the
 * classes they have in common, and they have plenty in common, because the
 * bindings from `gacela.php` reach all of them.
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
