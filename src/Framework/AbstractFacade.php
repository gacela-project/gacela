<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

/**
 * @template TFactory of AbstractFactory = AbstractFactory
 */
abstract class AbstractFacade
{
    /** @var array<string, AbstractFactory> */
    private static array $factories = [];

    public static function resetCache(): void
    {
        self::$factories = [];
    }

    /**
     * @return TFactory
     */
    public function getFactory(): AbstractFactory
    {
        $factory = self::$factories[static::class]
            ??= (new FactoryResolver())->resolve(static::class);

        /** @var TFactory $factory */
        return $factory;
    }
}
