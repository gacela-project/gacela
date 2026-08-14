<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingIfPlugin;

use Gacela\Framework\Bootstrap\GacelaConfig;

/**
 * What a package ships for its consumers to `extendGacelaConfig()`: a working
 * default that gets out of the way the moment the application states its own.
 */
final class PackageGacelaConfig
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addBindingIf(Clock::class, PackageDefaultClock::class);
    }
}
