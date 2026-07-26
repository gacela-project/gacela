<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use RuntimeException;

use function sprintf;

final class HealthCheckNotResolvableException extends RuntimeException
{
    public static function classNotFound(string $className): self
    {
        return new self(sprintf(
            'The health check "%s" was registered but the class does not exist. '
            . 'Check the class-string passed to GacelaConfig::addHealthCheck().',
            $className,
        ));
    }

    public static function notAHealthCheck(string $className): self
    {
        return new self(sprintf(
            'The health check "%s" does not implement %s.',
            $className,
            ModuleHealthCheckInterface::class,
        ));
    }
}
