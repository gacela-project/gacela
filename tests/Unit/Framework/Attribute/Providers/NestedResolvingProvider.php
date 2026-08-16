<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * A provided method resolving *another* provided id, which is ordinary wiring
 * and the thing the cycle guard must not break.
 */
final class NestedResolvingProvider extends AbstractProvider
{
    public const string INNER_ID = 'nested_inner';

    public const string OUTER_ID = 'nested_outer';

    #[Provides(self::INNER_ID)]
    public function inner(): string
    {
        return 'inner';
    }

    #[Provides(self::OUTER_ID)]
    public function outer(Container $container): string
    {
        /** @var string $inner */
        $inner = $container->get(self::INNER_ID);

        return $inner . '+outer';
    }
}
