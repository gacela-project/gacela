<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;

final class ProviderWithPrivateAttribute extends AbstractProvider
{
    #[Provides('public_one')]
    public function publicOne(): string
    {
        return 'public';
    }
}
