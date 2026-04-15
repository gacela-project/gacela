<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class ProviderWithMixedStyles extends AbstractProvider
{
    public const ATTRIBUTE_ID = 'mixed_attribute';

    public const MANUAL_ID = 'mixed_manual';

    #[Provides(self::ATTRIBUTE_ID)]
    public function fromAttribute(): string
    {
        return 'from-attribute';
    }

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::MANUAL_ID, static fn (): string => 'from-manual');
    }
}
