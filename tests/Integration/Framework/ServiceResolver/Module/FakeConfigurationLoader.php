<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

/**
 * A plain service whose name happens to contain `Config`, the way
 * `ConfigurationLoader`, `ConfigReader` or the framework's own `GacelaConfig`
 * and `ConfigFactory` do. It is not a pillar and does not extend one.
 */
final class FakeConfigurationLoader
{
    public function name(): string
    {
        return 'the real loader';
    }
}
