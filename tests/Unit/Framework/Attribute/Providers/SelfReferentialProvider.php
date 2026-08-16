<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * The declaration from #870: the body resolves the id the method provides.
 *
 * A typo rather than a design -- the author meant a concrete class and wrote
 * the binding id -- which is why it has to be caught rather than documented.
 */
final class SelfReferentialProvider extends AbstractProvider
{
    public const string SELF_ID = 'self_referential_service';

    public const string INDIRECT_ID = 'indirect_service';

    public const string SOUND_ID = 'sound_service';

    #[Provides(self::SELF_ID)]
    public function selfReferential(Container $container): mixed
    {
        return $container->get(self::SELF_ID);
    }

    /**
     * One step away: the body resolves a different id whose own body comes
     * back. Nothing about the loop is visible in either method.
     */
    #[Provides(self::INDIRECT_ID)]
    public function indirect(Container $container): mixed
    {
        return $container->get(self::SOUND_ID);
    }

    #[Provides(self::SOUND_ID)]
    public function sound(Container $container): mixed
    {
        return $container->get(self::INDIRECT_ID);
    }
}
