<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Attribute\Cacheable;
use ReflectionClass;
use ReflectionMethod;

use function count;
use function sprintf;

/**
 * Reports `#[Cacheable]` methods running on the in-memory default storage.
 *
 * The default backend lives and dies with the process. Under PHP-FPM that is
 * one request, so a method annotated for an hour's TTL is recomputed every
 * time and the attribute buys nothing -- silently, because a cache that misses
 * is indistinguishable from one that was never asked to hold anything.
 *
 * A warning rather than an error, because the default is right for anything
 * that is not per-request: a CLI batch, a queue worker, a long-running server
 * where the process outlives the work. `--strict` is how a project that
 * deploys under FPM opts into failing on it.
 */
final class CacheableStorageCheck implements HealthCheck
{
    /**
     * @param list<AppModule> $modules
     */
    public function __construct(
        private readonly array $modules,
        private readonly bool $hasUserSuppliedStorage,
    ) {
    }

    public function name(): string
    {
        return 'cacheable storage';
    }

    public function run(): CheckResult
    {
        $cacheable = $this->cacheableMethods();

        if ($cacheable === []) {
            return CheckResult::ok($this->name(), 'no #[Cacheable] methods to store');
        }

        if ($this->hasUserSuppliedStorage) {
            return CheckResult::ok($this->name(), sprintf(
                '%d #[Cacheable] method(s) on a registered backend',
                count($cacheable),
            ));
        }

        return CheckResult::warn(
            $this->name(),
            $cacheable,
            'the default storage lives and dies with the process, so under PHP-FPM these are '
            . 'recomputed every request — register a cross-request backend with '
            . '`CacheableConfig::setStorage()`, or ignore this where the process outlives the work',
        );
    }

    /**
     * @return list<string>
     */
    private function cacheableMethods(): array
    {
        $found = [];

        foreach ($this->modules as $module) {
            foreach ($this->pillarsOf($module) as $pillarClass) {
                foreach ($this->cacheableMethodsOf($pillarClass) as $method) {
                    $found[] = $method;
                }
            }
        }

        return $found;
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    private function cacheableMethodsOf(string $className): array
    {
        // No guard around this: the pillars arrive as `class-string`, so the
        // reflection cannot fail, and a catch here would be a branch no input
        // can reach.
        $reflection = new ReflectionClass($className);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(Cacheable::class) !== []) {
                $methods[] = sprintf('%s::%s()', $className, $method->getName());
            }
        }

        return $methods;
    }

    /**
     * @return list<class-string>
     */
    private function pillarsOf(AppModule $module): array
    {
        $pillars = [$module->facadeClass()];

        foreach ([$module->factoryClass(), $module->configClass(), $module->providerClass()] as $pillar) {
            if ($pillar !== null) {
                $pillars[] = $pillar;
            }
        }

        return $pillars;
    }
}
