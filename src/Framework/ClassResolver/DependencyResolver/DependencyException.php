<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use RuntimeException;

final class DependencyException extends RuntimeException
{
    public static function noParameterTypeFor(string $parameter): self
    {
        return new self("No parameter type for '{$parameter}'");
    }

    public static function unableToResolve(string $parameter, string $className): self
    {
        return new self("Unable to resolve [{$parameter}] from {$className}");
    }

    public static function mapNotFoundForClassName(string $className): self
    {
        $message = <<<TXT
Did you forget to map this interface to a concrete class? No concrete class was found that implements:
{$className}
TXT;
        return new self($message);
    }
}
