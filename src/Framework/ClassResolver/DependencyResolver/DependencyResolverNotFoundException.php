<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use RuntimeException;

final class DependencyResolverNotFoundException extends RuntimeException
{
    public static function forClassName(string $className): self
    {
        $message = <<<TXT
No concrete class was found that implements:
{$className}
Did you forget to map this interface to a concrete class in gacela.php overriding the mappingInterfaces() method?
TXT;
        return new self($message);
    }
}
