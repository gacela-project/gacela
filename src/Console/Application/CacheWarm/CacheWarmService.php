<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

use function array_filter;
use function class_exists;
use function str_contains;

final class CacheWarmService
{
    public function __construct(
        private readonly ConsoleFacade $facade,
    ) {
    }

    /**
     * @return list<AppModule>
     */
    public function discoverModules(): array
    {
        try {
            return $this->facade->findAllAppModules();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return list<AppModule>
     */
    public function filterProductionModules(array $modules): array
    {
        return array_filter($modules, static function ($module): bool {
            $className = $module->facadeClass();
            return !str_contains($className, 'Test')
                && !str_contains($className, '\\Fixtures\\')
                && !str_contains($className, '\\Benchmark\\');
        });
    }

    /**
     * @return array{type: string, className: string}[]
     */
    public function getModuleClasses(AppModule $module): array
    {
        $classes = [
            ['type' => 'Facade', 'className' => $module->facadeClass()],
        ];

        if ($module->factoryClass() !== null) {
            $classes[] = ['type' => 'Factory', 'className' => $module->factoryClass()];
        }

        if ($module->configClass() !== null) {
            $classes[] = ['type' => 'Config', 'className' => $module->configClass()];
        }

        if ($module->providerClass() !== null) {
            $classes[] = ['type' => 'Provider', 'className' => $module->providerClass()];
        }

        return $classes;
    }

    public function resolveClass(string $className): void
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundException($className);
        }

        class_exists($className, true);
    }

    /**
     * Pre-warm the attribute cache by scanning all methods that might use ServiceMap attributes.
     *
     * @param class-string $className
     */
    public function warmAttributeCache(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }

        try {
            $reflectionClass = new ReflectionClass($className);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            // Create a DocBlockResolver instance to cache attribute resolutions
            $docBlockResolver = DocBlockResolver::fromClassName($className);

            // Common method patterns that typically use ServiceMap
            $commonMethodPrefixes = ['get', 'create', 'find', 'build'];

            foreach ($methods as $method) {
                $methodName = $method->getName();

                // Skip magic methods and constructors
                if (str_starts_with($methodName, '__')) {
                    continue;
                }

                // Check if method matches common service retrieval patterns
                foreach ($commonMethodPrefixes as $prefix) {
                    if (str_starts_with($methodName, $prefix)) {
                        // This will trigger attribute resolution and caching
                        $docBlockResolver->getDocBlockResolvable($methodName);
                        break;
                    }
                }
            }
        } catch (Throwable) {
            // Silently skip classes that can't be reflected or resolved
        }
    }
}
