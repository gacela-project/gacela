<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use ReflectionClass;
use Throwable;

use function strlen;

final class AppModuleCreator
{
    public function __construct(
        private readonly FactoryResolver $factoryResolver,
        private readonly ConfigResolver $configResolver,
        private readonly ProviderResolver $providerResolver,
    ) {
    }

    /**
     * @param class-string $facadeClass
     */
    public function fromClass(string $facadeClass): AppModule
    {
        return new AppModule(
            $this->fullModuleName($facadeClass),
            $this->moduleName($facadeClass),
            $facadeClass,
            $this->findFactory($facadeClass),
            $this->findConfig($facadeClass),
            $this->findProvider($facadeClass),
        );
    }

    /**
     * @param class-string $facadeClass
     */
    private function fullModuleName(string $facadeClass): string
    {
        $moduleNameIndex = strrpos($facadeClass, '\\') ?: strlen($facadeClass);

        return substr($facadeClass, 0, $moduleNameIndex);
    }

    /**
     * @param class-string $facadeClass
     */
    private function moduleName(string $facadeClass): string
    {
        $fullModuleName = $this->fullModuleName($facadeClass);

        $moduleName = strrchr($fullModuleName, '\\') ?: $fullModuleName;

        return ltrim($moduleName, '\\');
    }

    /**
     * @param class-string $facadeClass
     */
    private function findFactory(string $facadeClass): ?string
    {
        return $this->resolveClassName($this->factoryResolver, $facadeClass);
    }

    /**
     * @param class-string $facadeClass
     */
    private function findConfig(string $facadeClass): ?string
    {
        return $this->resolveClassName($this->configResolver, $facadeClass);
    }

    /**
     * @param class-string $facadeClass
     */
    private function findProvider(string $facadeClass): ?string
    {
        return $this->resolveClassName($this->providerResolver, $facadeClass);
    }

    /**
     * Resolve the concrete class name behind a facade for the given resolver,
     * or null when the module has none (resolution fails, resolves to null,
     * or falls back to an anonymous default class).
     *
     * @param class-string $facadeClass
     */
    private function resolveClassName(AbstractClassResolver $resolver, string $facadeClass): ?string
    {
        try {
            $resolved = $resolver->resolve($facadeClass);
        } catch (Throwable) {
            return null;
        }

        if ($resolved === null || (new ReflectionClass($resolved))->isAnonymous()) {
            return null;
        }

        return $resolved::class;
    }
}
