<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class ProviderWithoutAttributes extends AbstractProvider
{
    public const MANUAL_ID = 'only_manual';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::MANUAL_ID, static fn (): string => 'manual-only');
    }
}
