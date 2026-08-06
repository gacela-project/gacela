<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

/**
 * @template TFactory of AbstractFactory = AbstractFactory
 */
abstract class AbstractFacade
{
    use CacheableTrait;

    /** @var array<string, AbstractFactory> */
    private static array $factories = [];

    public static function resetCache(): void
    {
        self::$factories = [];

        // Deliberately not clearMethodCache(): that clears whatever backend is
        // registered, and this path is reached by every Gacela::resetCache(),
        // including the one GacelaTestCase runs per test.
        CacheableConfig::clearDefaultStorage();
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
