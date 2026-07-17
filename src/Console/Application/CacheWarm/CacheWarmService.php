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
        return $this->facade->findAllAppModules();
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
     * @return list<array{type: string, className: class-string}>
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

            $docBlockResolver = DocBlockResolver::fromClassName($className);

            $commonMethodPrefixes = ['get', 'create', 'find', 'build'];

            foreach ($methods as $method) {
                $methodName = $method->getName();

                if (str_starts_with($methodName, '__')) {
                    continue;
                }

                foreach ($commonMethodPrefixes as $prefix) {
                    if (str_starts_with($methodName, $prefix)) {
                        // Called for its side effect: resolving populates the attribute cache.
                        $docBlockResolver->getDocBlockResolvable($methodName);
                        break;
                    }
                }
            }
        } catch (Exception) {
            // Skip classes whose attributes/doc-blocks cannot be resolved during
            // warm (user code may be un-constructible here). Errors (i.e. actual
            // programming bugs) intentionally propagate so they are not hidden.
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
