<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

use RuntimeException;

final class MissingMethodException extends RuntimeException
{
    public static function missingOverriding(string $method, string $className): self
    {
        return new self("Class not found for '{$method}' in your PHPDoc '{$className}' neither as override method.");
    }
}
