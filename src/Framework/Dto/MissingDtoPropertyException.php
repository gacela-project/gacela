<?php

declare(strict_types=1);

namespace Gacela\Framework\Dto;

use RuntimeException;

use function sprintf;

/**
 * Thrown by a generated class when a required property is read before anything
 * set it.
 *
 * Lives in the framework rather than beside the generated files: it is part of
 * the contract a consumer catches, and a generated exception class would be
 * deleted and rewritten on every regeneration.
 */
final class MissingDtoPropertyException extends RuntimeException
{
    public static function forProperty(string $className, string $property): self
    {
        return new self(sprintf(
            '"%s::$%s" is required but was never set.',
            $className,
            $property,
        ));
    }
}
