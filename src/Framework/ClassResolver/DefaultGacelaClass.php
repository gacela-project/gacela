<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

/**
 * Fallback instances used when no class could be resolved for a caller.
 *
 * Keyed on the resolvable type rather than on the resolver's class identity, so
 * the fixed resolvers and the dynamically-typed {@see \Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver}
 * share one definition. Types without a meaningful empty implementation
 * (Provider, DependencyProvider, custom types) resolve to null.
 *
 * @internal
 */
final class DefaultGacelaClass
{
    public static function forType(string $resolvableType): ?object
    {
        return match ($resolvableType) {
            FacadeResolver::TYPE => new /**
             * @extends AbstractFacade<AbstractFactory>
             */ class() extends AbstractFacade {},
            FactoryResolver::TYPE => new /**
             * @extends AbstractFactory<AbstractConfig>
             */ class() extends AbstractFactory {},
            ConfigResolver::TYPE => new class() extends AbstractConfig {},
            default => null,
        };
    }
}
