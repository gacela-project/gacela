<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use RuntimeException;

final class DependencyResolverNotFoundException extends RuntimeException
{
    public function __construct(string $className)
    {
        $message = <<<TXT
No concrete class was found that implements:
{$className}
Did you forget to map this interface to a concrete class in gacela.php overriding the mappingInterfaces() method?
TXT;
        parent::__construct($message);
    }
}
