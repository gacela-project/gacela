<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * The other half: it provides the same id as {@see SharedIdProviderTwo} and
 * resolves that one's, from that one's container. Same id, twice, on the
 * resolution stack at once -- and not a cycle, because the second call is a
 * different declaration.
 *
 * The method is deliberately named the same as its counterpart, so only the
 * provider class tells the two frames apart.
 */
final class SharedIdProviderOne extends AbstractProvider
{
    public const string SHARED_ID = SharedIdProviderTwo::SHARED_ID;

    public function __construct(
        private readonly Container $otherModuleContainer,
    ) {
    }

    #[Provides(self::SHARED_ID)]
    public function shared(): string
    {
        /** @var string $other */
        $other = $this->otherModuleContainer->get(self::SHARED_ID);

        return 'from-one+' . $other;
    }
}
