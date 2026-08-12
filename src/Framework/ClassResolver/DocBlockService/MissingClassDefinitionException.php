<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use RuntimeException;

final class MissingClassDefinitionException extends RuntimeException
{
    public static function missingDefinition(string $className, string $method, string $found): self
    {
        $suggestions = self::getSuggestions($method);

        return new self("
Missing the concrete return type for the method `{$method}()` (Found: `{$found}`).

Class: `{$className}`

Possible solutions:
{$suggestions}

Learn more: https://gacela-project.com/docs/service-resolution
");
    }

    private static function getSuggestions(string $method): string
    {
        $suggestions = [];

        $suggestions[] = "1. Declare it with the #[ServiceMap] attribute (recommended):

   use Gacela\Framework\ServiceResolver\ServiceMap;

   #[ServiceMap(method: '{$method}', className: YourClass::class)]
   final class YourClass
   {
       use ServiceResolverAwareTrait;
   }";

        $suggestions[] = "2. Add a @method DocBlock (deprecated, removed in 3.0):

   /**
    * @method YourClass {$method}()
    */
   final class YourClass
   {
       use ServiceResolverAwareTrait;
   }";

        return implode("\n\n", $suggestions);
    }
}
