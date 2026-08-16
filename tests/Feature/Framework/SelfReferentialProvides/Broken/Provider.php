<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\SelfReferentialProvides\Broken;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const string SOUND_ID = 'self-referential-sound';

    public const string SELF_REFERENTIAL_ID = 'self-referential-broken';

    #[Provides(self::SOUND_ID)]
    public function sound(): string
    {
        return 'sound';
    }

    /**
     * The declaration from #870: a typo, where the author meant a concrete
     * class and wrote the binding id.
     *
     * Only this one id is poisoned, so the module resolves like any other and
     * the failure is reached by asking for it.
     */
    #[Provides(self::SELF_REFERENTIAL_ID)]
    public function selfReferential(Container $container): mixed
    {
        return $container->get(self::SELF_REFERENTIAL_ID);
    }
}
