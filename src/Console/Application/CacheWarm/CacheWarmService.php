<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
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
}
