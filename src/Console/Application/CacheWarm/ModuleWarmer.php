<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Console\Domain\AllAppModules\AppModule;
use Throwable;

/**
 * Warms the cache for one module at a time: resolves each pillar class,
 * optionally pre-warms its attribute cache, and reports progress through
 * the output formatter.
 *
 * @psalm-type WarmStats = array{int, int}
 */
final class ModuleWarmer
{
    public function __construct(
        private readonly CacheWarmService $cacheWarmService,
        private readonly CacheWarmOutputFormatter $formatter,
    ) {
    }

    /**
     * Warm modules sequentially.
     *
     * @param list<AppModule> $modules
     *
     * @return WarmStats [resolvedCount, skippedCount]
     */
    public function warmModules(array $modules, bool $warmAttributes): array
    {
        $resolvedCount = 0;
        $skippedCount = 0;

        foreach ($modules as $module) {
            [$resolved, $skipped] = $this->warmModule($module, $warmAttributes);
            $resolvedCount += $resolved;
            $skippedCount += $skipped;
        }

        return [$resolvedCount, $skippedCount];
    }

    /**
     * Warm a single module's cache.
     *
     * @return WarmStats [resolvedCount, skippedCount]
     */
    public function warmModule(AppModule $module, bool $warmAttributes): array
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

        $this->cacheWarmService->warmClassResolution($module->facadeClass());

        $this->formatter->writeEmptyLine();

        return [$resolvedCount, $skippedCount];
    }
}
