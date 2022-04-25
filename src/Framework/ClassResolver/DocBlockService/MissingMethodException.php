<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use RuntimeException;

final class MissingMethodException extends RuntimeException
{
    public static function missingOverriding(string $method, string $className, string $found): self
    {
        return new self("The method '{$method}()' is pointing to '{$found}'.\nExpected fully-qualified class in your PHPDoc: '{$className}'.");
    }
}
