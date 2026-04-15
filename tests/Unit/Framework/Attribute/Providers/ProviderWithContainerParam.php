<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class ProviderWithContainerParam extends AbstractProvider
{
    #[Provides('container_class')]
    public function containerClass(Container $container): string
    {
        return $container::class;
    }

    #[Provides('paramless')]
    public function paramless(): string
    {
        return 'no-container';
    }
}
