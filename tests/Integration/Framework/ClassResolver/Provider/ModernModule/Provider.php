<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider\ModernModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Counts how often Gacela invokes `provideModuleDependencies()`, so a test can
 * assert a module's provider body runs exactly once per container build.
 */
final class Provider extends AbstractProvider
{
    public const GREETING = 'modern-provider-greeting';

    public static int $provideCallCount = 0;

    public static function resetCallCount(): void
    {
        self::$provideCallCount = 0;
    }

    public function provideModuleDependencies(Container $container): void
    {
        ++self::$provideCallCount;

        $container->set(self::GREETING, static fn (): string => 'hello from the modern provider');
    }
}
