<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Exception;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\DependencyProviderResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use Gacela\Framework\ServiceResolver\ReflectionClassPool;
use ReflectionMethod;
use Throwable;

use function array_filter;
use function class_exists;
use function str_contains;

final class CacheWarmService
{
    /** @var list<AbstractClassResolver>|null */
    private ?array $classResolvers = null;

    public function __construct(
        private readonly ConsoleFacade $facade,
    ) {
    }

    /**
     * Eagerly resolve a module's Factory, Config, and Provider through Gacela's
     * class resolvers so the on-disk ClassNamePhpCache is populated at warm time
     * rather than paying the namespaces x rules x types x class_exists lookup
     * on the first request to each module.
     *
     * @param class-string $facadeClass
     */
    public function warmClassResolution(string $facadeClass): void
    {
        if (!class_exists($facadeClass)) {
            return;
        }

        foreach ($this->classResolvers() as $resolver) {
            try {
                $resolver->resolve($facadeClass);
            } catch (Exception) {
                // A module may legitimately lack a Factory/Config/Provider, or
                // its dependencies may not be constructible during warm; skip.
            }
        }
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
        return array_filter($modules, static function (\Gacela\Console\Domain\AllAppModules\AppModule $module): bool {
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
            $reflectionClass = ReflectionClassPool::get($className);
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

    /**
     * @return list<AbstractClassResolver>
     */
    private function classResolvers(): array
    {
        return $this->classResolvers ??= [
            new FactoryResolver(),
            new ConfigResolver(),
            new ProviderResolver(),
            new DependencyProviderResolver(),
        ];
    }
}
