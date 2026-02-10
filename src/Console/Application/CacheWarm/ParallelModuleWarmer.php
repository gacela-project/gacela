<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Fiber;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Throwable;

/**
 * Warms module cache using PHP 8.1 Fibers for parallel resolution.
 *
 * This provides significant performance improvements for large projects
 * by processing multiple modules concurrently.
 */
final class ParallelModuleWarmer
{
    /** @var list<Fiber> */
    private array $activeFibers = [];

    public function __construct(
        private readonly CacheWarmService $cacheWarmService,
        private readonly CacheWarmOutputFormatter $formatter,
        private readonly int $maxConcurrency = 5,
    ) {
    }

    /**
     * Warm modules cache in parallel using Fibers.
     *
     * @param list<AppModule> $modules
     * @param bool $warmAttributes Whether to pre-warm attribute cache
     *
     * @return array{int, int} [resolvedCount, skippedCount]
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

            // Create a new fiber for this module
            $fiber = new Fiber(function () use ($module, $warmAttributes): array {
                return $this->warmModule($module, $warmAttributes);
            });

            $fiber->start();
            $this->activeFibers[] = $fiber;
        }

        // Process any remaining fibers
        while (count($this->activeFibers) > 0) {
            [$resolved, $skipped] = $this->processCompletedFibers();
            $resolvedCount += $resolved;
            $skippedCount += $skipped;
        }

        return [$resolvedCount, $skippedCount];
    }

    /**
     * Process completed fibers and remove them from the active list.
     *
     * @return array{int, int} [resolvedCount, skippedCount]
     */
    private function processCompletedFibers(): array
    {
        $resolvedCount = 0;
        $skippedCount = 0;

        foreach ($this->activeFibers as $index => $fiber) {
            if ($fiber->isTerminated()) {
                try {
                    [$resolved, $skipped] = $fiber->getReturn();
                    $resolvedCount += $resolved;
                    $skippedCount += $skipped;
                } catch (Throwable) {
                    // Fiber failed, count as skipped
                    $skippedCount++;
                }

                unset($this->activeFibers[$index]);
            }
        }

        // Re-index the array
        $this->activeFibers = array_values($this->activeFibers);

        // Give other fibers a chance to complete
        if (count($this->activeFibers) > 0) {
            usleep(1000); // 1ms
        }

        return [$resolvedCount, $skippedCount];
    }

    /**
     * Warm a single module's cache.
     *
     * @return array{int, int} [resolvedCount, skippedCount]
     */
    private function warmModule(AppModule $module, bool $warmAttributes): array
    {
        $resolvedCount = 0;
        $skippedCount = 0;

        $this->formatter->writeModuleName($module->moduleName());

        $moduleClasses = $this->cacheWarmService->getModuleClasses($module);

        foreach ($moduleClasses as $classInfo) {
            try {
                $this->cacheWarmService->resolveClass($classInfo['className']);

                if ($warmAttributes) {
                    $this->cacheWarmService->warmAttributeCache($classInfo['className']);
                }

                $this->formatter->writeClassResolved($classInfo['type'], $classInfo['className']);
                ++$resolvedCount;
            } catch (ClassNotFoundException) {
                $this->formatter->writeClassSkipped($classInfo['type'], $classInfo['className']);
                ++$skippedCount;
            } catch (Throwable $e) {
                $this->formatter->writeClassFailed($classInfo['type'], $classInfo['className'], $e->getMessage());
                ++$skippedCount;
            }
        }

        $this->formatter->writeEmptyLine();

        return [$resolvedCount, $skippedCount];
    }
}
