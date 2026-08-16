<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;

/**
 * Half of the shape the cycle guard must not mistake for a cycle: two modules
 * legitimately providing the same id, each answering for itself.
 */
final class SharedIdProviderTwo extends AbstractProvider
{
    public const string SHARED_ID = 'shared_id';

    #[Provides(self::SHARED_ID)]
    public function shared(): string
    {
        return 'from-two';
    }
}
