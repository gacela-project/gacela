<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use Gacela\Framework\Exception\ErrorSuggestionHelper;
use RuntimeException;

use function sprintf;

final class HealthCheckNotResolvableException extends RuntimeException
{
    /**
     * A registered class-string that resolves to nothing is usually a namespace
     * typo or an autoloader that has not seen the file yet, so this carries the
     * tips for that alongside the call to check.
     */
    public static function classNotFound(string $className): self
    {
        return new self(sprintf(
            'The health check "%s" was registered but the class does not exist. '
            . 'Check the class-string passed to GacelaConfig::addHealthCheck().',
            $className,
        ) . ErrorSuggestionHelper::addHelpfulTip('class_not_found'));
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
