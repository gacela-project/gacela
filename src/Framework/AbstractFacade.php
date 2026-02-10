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
abstract class AbstractFacade
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
}
