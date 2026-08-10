<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge\Attribute;

use Attribute;
use Gacela\Container\Attribute\Inject as ContainerInject;
use Gacela\Framework\Gacela;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;

/**
 * `#[Inject]` that Laravel's container honors natively.
 *
 * Extends the Gacela attribute -- non-`final` for exactly this, and every
 * attribute read in Gacela passes `IS_INSTANCEOF`, so the subclass is honored
 * there too. One attribute serves both containers: Gacela resolves it on the
 * classes it builds, Laravel resolves it on the classes *it* builds, through
 * the `ContextualAttribute` contract.
 *
 * On a constructor parameter the implementation is required: Laravel hands a
 * contextual attribute no parameter to read a type from. On a property or a
 * setter the type is on the member, so the bare form works -- that path is
 * {@see \Gacela\LaravelBridge\GacelaInjectListener}'s.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Inject extends ContainerInject implements ContextualAttribute
{
    /**
     * @throws BindingResolutionException
     */
    public static function resolve(self $attribute, Container $container): object
    {
        $target = $attribute->implementation
            ?? throw new BindingResolutionException(
                '#[Inject] on a constructor parameter resolved by Laravel needs an explicit class,'
                . ' e.g. #[Inject(ProductFacade::class)]: Laravel hands a contextual attribute no'
                . ' parameter to infer a type from. On a property or a setter the bare form works.',
            );

        return Gacela::getRequired($target);
    }
}
