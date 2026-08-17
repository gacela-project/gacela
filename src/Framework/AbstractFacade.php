<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

/**
 * One pillar accessor lives here, {@see getFactory()}, and it is the only one a
 * Facade has for free. `gacela.facadeOnlyDelegates` accepts three more roots,
 * and each arrives with the trait that declares it: `ConfigResolverAwareTrait`
 * for `getConfig()`, `DeclaredTypeResolverAwareTrait` for `getResolvedType()`,
 * `ServiceResolverAwareTrait` plus a `#[ServiceMap]` accessor for
 * `getProvider()`. Writing one without its trait is `Call to undefined method`
 * at runtime, so the rule's tip names them too.
 *
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
        CacheableConfig::clearFrameworkOwnedStorage();
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
