<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use Gacela\Framework\Container\Container;
use ReflectionClass;

use function class_exists;

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

    /**
     * @param ?Container $container used to resolve registered class-string checks;
     *                              when null, checks are instantiated directly
     */
    public static function createHealthChecker(?Container $container = null): HealthChecker
    {
        return new HealthChecker(self::resolveAll($container));
    }

    /**
     * @return list<ModuleHealthCheckInterface>
     */
    private static function resolveAll(?Container $container): array
    {
        $resolved = [];

        foreach (self::$checks as $check) {
            if ($check instanceof ModuleHealthCheckInterface) {
                $resolved[] = $check;
                continue;
            }

            $resolved[] = self::instantiate($check, $container);
        }

        return $resolved;
    }

    /**
     * The registry stores whatever `addHealthCheck()` was handed. Its
     * `class-string<ModuleHealthCheckInterface>` annotation is a promise from
     * the caller, not a verified fact, so this takes a bare `class-string` and
     * checks both halves of that promise at runtime.
     *
     * @param class-string $className
     *
     * @throws HealthCheckNotResolvableException
     */
    private static function instantiate(string $className, ?Container $container): ModuleHealthCheckInterface
    {
        if ($container instanceof Container) {
            /** @var mixed $instance */
            $instance = $container->get($className);
            if ($instance instanceof ModuleHealthCheckInterface) {
                return $instance;
            }
        }

        if (!class_exists($className)) {
            throw HealthCheckNotResolvableException::classNotFound($className);
        }

        $instance = (new ReflectionClass($className))->newInstance();
        if (!$instance instanceof ModuleHealthCheckInterface) {
            throw HealthCheckNotResolvableException::notAHealthCheck($className);
        }

        return $instance;
    }
}
