<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Exception;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use Gacela\Framework\ServiceResolver\ReflectionClassPool;
use Gacela\Framework\ServiceResolver\ServiceMapAccessors;

use function array_filter;
use function array_values;
use function class_exists;
use function str_contains;

final class CacheWarmService
{
    /** @var list<AbstractClassResolver>|null */
    private ?array $classResolvers = null;

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
     * @param list<AppModule> $modules
     *
     * @return list<AppModule>
     */
    public function filterProductionModules(array $modules): array
    {
        return array_values(array_filter($modules, static function (\Gacela\Console\Domain\AllAppModules\AppModule $module): bool {
            $className = $module->facadeClass();
            // Anchor to whole namespace segments (like \Fixtures\ / \Benchmark\); an unanchored
            // 'Test' substring dropped legitimate modules such as App\Testimonial\TestimonialFacade.
            return !str_contains($className, '\\Test\\')
                && !str_contains($className, '\\Tests\\')
                && !str_contains($className, '\\Fixtures\\')
                && !str_contains($className, '\\Benchmark\\');
        }));
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

    /**
     * class_exists() autoloads by default, so the guard below is what actually
     * loads the class; a second autoloading call would be a no-op.
     */
    public function resolveClass(string $className): void
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundException($className);
        }
    }

    /**
     * Pre-resolve the pillar accessors this class declares with `#[ServiceMap]`.
     *
     * The entries are keyed by caller-and-method and read from exactly one
     * place: `ServiceResolverAwareTrait::__call()`, which PHP invokes only for
     * a method the class **does not have**. So the set worth warming is the
     * accessors the attribute declares, minus any the class really declares --
     * an entry under a real method's name is one nothing can ever look up.
     *
     * It used to walk `getMethods(IS_PUBLIC)` instead, resolving whichever
     * started with get/create/find/build. Every one of those is a real method by
     * definition, so the whole pass wrote unreachable entries -- and, on any
     * class extending `AbstractFacade`, resolved the inherited real
     * `getFactory()` through the docblock fallback and raised the 3.0
     * deprecation for it. That accessor resolves through `FactoryResolver` and
     * the module's naming convention, so the notice named a call that does not
     * take that path and suggested an attribute that would not change it: one
     * per facade in the project, on the command `UPGRADE.md` recommends for
     * surfacing genuine ones.
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
            $docBlockResolver = DocBlockResolver::fromClassName($className);

            foreach (array_keys(ServiceMapAccessors::declaredOn($reflectionClass)) as $methodName) {
                if ($reflectionClass->hasMethod($methodName)) {
                    continue;
                }

                // Called for its side effect: resolving populates the attribute cache.
                $docBlockResolver->getDocBlockResolvable($methodName);
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
        ];
    }
}
