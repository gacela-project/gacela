<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Fiber;
use Gacela\Console\Domain\AllAppModules\AppModule;

use function count;

/**
 * Warms module cache using PHP 8.1 Fibers for parallel resolution.
 *
 * This provides significant performance improvements for large projects
 * by processing multiple modules concurrently.
 *
 * @psalm-import-type WarmStats from ModuleWarmer
 *
 * @psalm-suppress TooManyTemplateParams Psalm's Fiber stub is not generic; the Fiber template params are for phpstan.
 */
final class ParallelModuleWarmer
{
    private readonly ModuleWarmer $moduleWarmer;

    /**
     * @psalm-suppress TooManyTemplateParams
     *
     * @var list<Fiber<null, null, WarmStats, null>>
     */
    private array $activeFibers = [];

    public function __construct(
        CacheWarmService $cacheWarmService,
        CacheWarmOutputFormatter $formatter,
        private readonly int $maxConcurrency = 5,
    ) {
        $this->moduleWarmer = new ModuleWarmer($cacheWarmService, $formatter);
    }

    /**
     * Warm modules cache in parallel using Fibers.
     *
     * @param list<AppModule> $modules
     * @param bool $warmAttributes Whether to pre-warm attribute cache
     *
     * @return WarmStats [resolvedCount, skippedCount]
     */
    public function warmModules(array $modules, bool $warmAttributes): array
    {
        $resolvedCount = 0;
        $skippedCount = 0;

        foreach ($modules as $module) {
            // Wait if we've reached max concurrency
            while (count($this->activeFibers) >= $this->maxConcurrency) {
                [$resolved, $skipped] = $this->processCompletedFibers();
                $resolvedCount += $resolved;
                $skippedCount += $skipped;
            }

            $fiber = new Fiber(fn (): array => $this->moduleWarmer->warmModule($module, $warmAttributes));

            $fiber->start();
            $this->activeFibers[] = $fiber;
        }

        // Drain the remaining fibers.
        while ($this->activeFibers !== []) {
            [$resolved, $skipped] = $this->processCompletedFibers();
            $resolvedCount += $resolved;
            $skippedCount += $skipped;
        }

        return [$resolvedCount, $skippedCount];
    }

    /**
     * Process completed fibers and remove them from the active list.
     *
     * @return WarmStats [resolvedCount, skippedCount]
     */
    private function processCompletedFibers(): array
    {
        $resolvedCount = 0;
        $skippedCount = 0;

        $stillActive = [];
        foreach ($this->activeFibers as $fiber) {
            if (!$fiber->isTerminated()) {
                $stillActive[] = $fiber;
                continue;
            }

            // These fibers never suspend, so a fiber that threw would have
            // propagated its exception at start(); a terminated fiber here
            // always has a return value.
            /** @var WarmStats $warmStats */
            $warmStats = $fiber->getReturn();
            [$resolved, $skipped] = $warmStats;
            $resolvedCount += $resolved;
            $skippedCount += $skipped;
        }

        $this->activeFibers = $stillActive;

        // Give other fibers a chance to complete
        if ($this->activeFibers !== []) {
            usleep(1000); // 1ms
        }

        return [$resolvedCount, $skippedCount];
    }
}
