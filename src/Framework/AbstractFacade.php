<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

/**
 * Base class for module facades.
 *
 * @template TFactory of AbstractFactory
 *
 * @psalm-consistent-constructor
 */
abstract class AbstractFacade implements ModuleDependenciesInterface
{
    /** @var array<class-string<self>, AbstractFactory> */
    private static array $factories = [];

    public static function resetCache(): void
    {
        self::$factories = [];
    }

    /**
     * Get the factory instance for this facade.
     *
     * @return TFactory
     *
     * @psalm-return TFactory
     */
    public function getFactory(): AbstractFactory
    {
        $factory = self::$factories[static::class]
            ??= (new FactoryResolver())->resolve(static::class);

        /** @var TFactory $factory */
        return $factory;
    }

    /**
     * Declare the module dependencies.
     *
     * Override this method to declare explicit dependencies on other modules.
     * This enables dependency graph visualization and circular dependency detection.
     *
     * @return array<class-string<AbstractFacade>> List of facade class names this module depends on
     */
    public function dependencies(): array
    {
        return [];
    }
}
