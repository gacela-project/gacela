<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use RuntimeException;

final class MissingClassDefinitionException extends RuntimeException
{
    public static function missingDefinition(string $className, string $method, string $found): self
    {
        return new self("
Missing the concrete return type for the method `{$method}()` (Found: `{$found}`).
Either fully-qualified or relative namespace are fine.
Did you forget to add the namespace as DocBlock for the class?
  Class -> `{$className}`
");
    }
}
