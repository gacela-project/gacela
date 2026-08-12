<?php

declare(strict_types=1);

namespace Gacela\Framework\Dto\Schema;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class MalformedDtoSchemaException extends RuntimeException
{
    public static function notAType(string $className, string $property): self
    {
        return new self(sprintf(
            'The declaration for "%s::$%s" must be a %s, declared with DtoType::string(), int(), float(), bool() or array().',
            $className,
            $property,
            DtoType::class,
        ));
    }

    public static function defaultOfWrongType(string $className, string $property, DtoType $type, mixed $default): self
    {
        return new self(sprintf(
            'The default for "%s::$%s" is %s, but the property is declared %s.',
            $className,
            $property,
            get_debug_type($default),
            $type->name,
        ));
    }

    public static function requiredWithDefault(string $className, string $property): self
    {
        return new self(sprintf(
            '"%s::$%s" is both required and defaulted. A default is what makes an absent value legitimate, so requiring it as well is two answers to one question.',
            $className,
            $property,
        ));
    }

    /**
     * The rule that makes a shape shareable: a package declares it, a project
     * adds to it, and neither can change what the other already compiled
     * against.
     */
    public static function conflictingRedeclaration(string $className, string $property): self
    {
        return new self(sprintf(
            '"%s::$%s" is declared twice with different shapes. A shape may be extended by another declarer, never redefined: the module that declared it first reads the same generated class, so changing the property under it would break code that already compiles.',
            $className,
            $property,
        ));
    }

    public static function notAValidClassName(string $className): self
    {
        return new self(sprintf(
            'The shape id "%s" is not a fully qualified class name. Declare a shape by the class it generates, for example "App\\Checkout\\Order", so the class lands where your own autoloader already looks for it.',
            $className,
        ));
    }

    public static function notAValidPropertyName(string $className, string $property): self
    {
        return new self(sprintf(
            'The property name "%s" declared on "%s" is not a valid php property name.',
            $property,
            $className,
        ));
    }
}
