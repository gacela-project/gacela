<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\ClassResolver\ResolvableTypes;
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
        $factory = $this->resolvePillar($this->factoryResolver, $facadeClass);
        $config = $this->resolvePillar($this->configResolver, $facadeClass);
        $provider = $this->resolvePillar($this->providerResolver, $facadeClass);

        return new AppModule(
            $this->fullModuleName($facadeClass),
            $this->moduleName($facadeClass),
            $facadeClass,
            $this->classNameOf($factory),
            $this->classNameOf($config),
            $this->classNameOf($provider),
            $this->failuresOf([
                ResolvableTypes::FACTORY => $factory,
                ResolvableTypes::CONFIG => $config,
                ResolvableTypes::PROVIDER => $provider,
            ]),
        );
    }

    /**
     * @param class-string $facadeClass
     */
    private function fullModuleName(string $facadeClass): string
    {
        // Not `?:` — a class in the root namespace yields position 0, which is a
        // valid separator index but falsy, and would fall through to strlen().
        $separatorIndex = strrpos($facadeClass, '\\');
        $moduleNameIndex = $separatorIndex === false ? strlen($facadeClass) : $separatorIndex;

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
     * The concrete class name behind a facade for the given resolver, or why
     * there is none: a `PillarResolutionFailure` when resolution threw, and
     * plain null when the module simply has no such pillar (nothing resolved,
     * or an anonymous default class stood in for one).
     *
     * The two used to be one answer. A `DependencyNotFoundException` out of the
     * pillar's own constructor was indistinguishable from a class that is not
     * there, which left `doctor` guessing at a cause with nothing to guess from
     * (#884, #890).
     *
     * @param class-string $facadeClass
     *
     * @return class-string|PillarResolutionFailure|null
     */
    private function resolvePillar(
        AbstractClassResolver $resolver,
        string $facadeClass,
    ): string|PillarResolutionFailure|null {
        try {
            $resolved = $resolver->resolve($facadeClass);
        } catch (Throwable $throwable) {
            return PillarResolutionFailure::from($throwable);
        }

        if ($resolved === null || (new ReflectionClass($resolved))->isAnonymous()) {
            return null;
        }

        return $resolved::class;
    }

    /**
     * @param class-string|PillarResolutionFailure|null $pillar
     *
     * @return ?class-string
     */
    private function classNameOf(string|PillarResolutionFailure|null $pillar): ?string
    {
        return $pillar instanceof PillarResolutionFailure ? null : $pillar;
    }

    /**
     * @param array<string, class-string|PillarResolutionFailure|null> $pillars
     *
     * @return array<string, PillarResolutionFailure>
     */
    private function failuresOf(array $pillars): array
    {
        $failures = [];

        foreach ($pillars as $kind => $pillar) {
            if ($pillar instanceof PillarResolutionFailure) {
                $failures[$kind] = $pillar;
            }
        }

        return $failures;
    }
}
