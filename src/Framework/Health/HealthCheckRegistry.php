<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;
use Throwable;

/**
 * Tracks health checks registered through GacelaConfig::addHealthCheck()
 * so they can be resolved into a HealthChecker at runtime.
 */
final class HealthCheckRegistry
{
    /** @var list<class-string<ModuleHealthCheckInterface>|ModuleHealthCheckInterface> */
    private static array $checks = [];

    /**
     * @param class-string<ModuleHealthCheckInterface>|ModuleHealthCheckInterface $check
     */
    public static function register(string|ModuleHealthCheckInterface $check): void
    {
        self::$checks[] = $check;
    }

    public static function reset(): void
    {
        self::$checks = [];
    }

    /**
     * @return list<class-string<ModuleHealthCheckInterface>|ModuleHealthCheckInterface>
     */
    public static function all(): array
    {
        return self::$checks;
    }

    public static function createHealthChecker(): HealthChecker
    {
        return new HealthChecker(self::resolveAll());
    }

    /**
     * @return list<ModuleHealthCheckInterface>
     */
    private static function resolveAll(): array
    {
        $container = self::resolveContainer();
        $resolved = [];

        foreach (self::$checks as $check) {
            if ($check instanceof ModuleHealthCheckInterface) {
                $resolved[] = $check;
                continue;
            }

            $instance = self::instantiate($check, $container);
            if ($instance instanceof ModuleHealthCheckInterface) {
                $resolved[] = $instance;
            }
        }

        return $resolved;
    }

    private static function resolveContainer(): ?Container
    {
        try {
            return Gacela::container();
        } catch (GacelaNotBootstrappedException) {
            return null;
        }
    }

    /**
     * @param class-string<ModuleHealthCheckInterface> $className
     */
    private static function instantiate(string $className, ?Container $container): ?ModuleHealthCheckInterface
    {
        if ($container instanceof Container) {
            try {
                /** @var mixed $instance */
                $instance = $container->get($className);
                if ($instance instanceof ModuleHealthCheckInterface) {
                    return $instance;
                }
            } catch (Throwable) {
                // fall through to direct instantiation
            }
        }

        if (!class_exists($className)) {
            return null;
        }

        return new $className();
    }
}
