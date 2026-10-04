<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\PlanCache;
use Throwable;

use function register_shutdown_function;

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
    public const FILENAME = 'gacela-container-plans.php';

    private static ?PlanCache $instance = null;

    private static ?string $file = null;

    private static int $countOnDisk = 0;

    private static bool $writeRegistered = false;

    public static function getInstance(): PlanCache
    {
        return self::$instance ??= new PlanCache();
    }

    /**
     * Start this process from the plans earlier ones saved in $file, and save
     * them back when it ends if it planned anything new. Under PHP-FPM every
     * request starts empty and reflects the same classes again; with this, the
     * first requests after a deploy plan them and the rest read them.
     *
     * The write happens once, at shutdown, and only when the count grew: a warm
     * application never writes. Entries whose class file changed are dropped
     * when read, so a deploy that forgets cache:clear plans those by reflection.
     */
    public static function persistIn(string $file): void
    {
        // A process that already planned (a worker re-bootstrapping) keeps what
        // it has: it is at least as current as the file.
        if (!self::$instance instanceof PlanCache) {
            self::$instance = PlanCache::fromFile($file);
            self::$countOnDisk = self::$instance->count();
        } elseif (self::$file !== $file) {
            self::$countOnDisk = 0;
        }

        self::$file = $file;

        if (!self::$writeRegistered) {
            self::$writeRegistered = true;
            register_shutdown_function(self::writeIfGrown(...));
        }
    }

    /**
     * @internal called at shutdown; public so a long-running process can save
     *   between jobs
     */
    public static function writeIfGrown(): void
    {
        if (self::$file === null || !self::$instance instanceof PlanCache || self::$instance->count() <= self::$countOnDisk) {
            return;
        }

        try {
            self::$instance->writeTo(self::$file);
            self::$countOnDisk = self::$instance->count();
        } catch (Throwable) {
            // Plans only save reflection. A cache directory that went away
            // or turned read-only costs the next request that, nothing more.
        }
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
        self::$file = null;
        self::$countOnDisk = 0;
    }
}
