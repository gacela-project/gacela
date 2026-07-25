<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider\LegacyModule;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

/**
 * Guards the backward-compatibility path for the deprecated AbstractDependencyProvider.
 * Delete together with AbstractDependencyProvider in version 2.0.
 */
final class DependencyProvider extends AbstractDependencyProvider
{
    public const GREETING = 'legacy-dependency-provider-greeting';

    public static int $provideCallCount = 0;

    public static function resetCallCount(): void
    {
        self::$provideCallCount = 0;
    }

    public function provideModuleDependencies(Container $container): void
    {
        ++self::$provideCallCount;

        $container->set(self::GREETING, static fn (): string => 'hello from the legacy dependency provider');
    }
}
